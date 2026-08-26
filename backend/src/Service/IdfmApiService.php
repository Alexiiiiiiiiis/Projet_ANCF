<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IdfmApiService
{
    private const DEPARTURES_TTL = 30;   // seconds
    private const DEPARTURES_DISPLAYED = 5;  // départs renvoyés par l'API
    private const DEPARTURES_KEPT = 40;      // profondeur gardée en cache, pour servir les filtres
    private const STOP_LINES_TTL = 86400; // 24 h — le code public d'une ligne ne bouge pas dans la journée
    private const STOPS_TTL = 300;       // 5 minutes
    private const JOURNEYS_TTL = 300;    // 5 minutes — mutualise le quota entre utilisateurs cherchant le même trajet
    // La clé PRIM a un quota strict de 1000 requêtes/jour partagé avec le reste de l'app (recherche
    // d'arrêts, horaires...). Une perturbation majeure garde rarement le même statut minute par
    // minute : 30 min est un compromis raisonnable entre fraîcheur et consommation du quota.
    private const ALERTS_TTL = 1800;     // 30 minutes
    private const ALERTS_PAGE_SIZE = 50;
    private const ALERTS_MAX_PAGES = 2;    // garde-fou : 100 perturbations max récupérées côté amont (flux global / bus)
    private const ALERTS_MAX_AGE = '-12 months'; // ignore les notices permanentes trop anciennes

    /**
     * Lignes du réseau structurant (RER, Métro) : couverture exhaustive, ligne par ligne.
     * Beaucoup de perturbations (ex. panne d'ascenseur) ne référencent qu'une station dans leurs
     * `impacted_objects`, sans objet "line" exploitable : connaître à l'avance la ligne interrogée
     * permet de leur attribuer le bon libellé/type plutôt que de deviner (cf. normalizeAlertsResponse).
     * Limité à RER+Métro (21 lignes, pas de Tram/Transilien) pour rester sous le quota API avec ces
     * 21 requêtes à chaque rafraîchissement de cache — le Tram/Transilien reste couvert par le flux
     * global si une perturbation y référence directement une ligne.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const STRUCTURING_LINES = [
        // RER — A, B, C, D, E
        'line:IDFM:C01742' => ['RER A', 'RER'], 'line:IDFM:C01743' => ['RER B', 'RER'],
        'line:IDFM:C01727' => ['RER C', 'RER'], 'line:IDFM:C01728' => ['RER D', 'RER'],
        'line:IDFM:C01729' => ['RER E', 'RER'],
        // Métro — 1 à 14 (+ 3bis, 7bis)
        'line:IDFM:C01371' => ['M1', 'METRO'], 'line:IDFM:C01372' => ['M2', 'METRO'],
        'line:IDFM:C01373' => ['M3', 'METRO'], 'line:IDFM:C01386' => ['M3B', 'METRO'],
        'line:IDFM:C01374' => ['M4', 'METRO'], 'line:IDFM:C01375' => ['M5', 'METRO'],
        'line:IDFM:C01376' => ['M6', 'METRO'], 'line:IDFM:C01377' => ['M7', 'METRO'],
        'line:IDFM:C01387' => ['M7B', 'METRO'], 'line:IDFM:C01378' => ['M8', 'METRO'],
        'line:IDFM:C01379' => ['M9', 'METRO'], 'line:IDFM:C01380' => ['M10', 'METRO'],
        'line:IDFM:C01381' => ['M11', 'METRO'], 'line:IDFM:C01382' => ['M12', 'METRO'],
        'line:IDFM:C01383' => ['M13', 'METRO'], 'line:IDFM:C01384' => ['M14', 'METRO'],
    ];

    /** Accents français (plus œ et æ) ramenés à leur lettre de base — cf. normaliserLibelle(). */
    private const EQUIVALENCES_ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
    ];

    /** Prefixe : SIRI et Navitia ont chacun leur compteur de 1000 requetes/jour chez PRIM. */
    /** Du mode le plus structurant au moins structurant : sert au tri comme au libellé d'un arrêt. */
    private const MODE_PRIORITY = ['METRO' => 1, 'RER' => 2, 'TRAM' => 3, 'BUS' => 4];

    private const QUOTA_CACHE_PREFIX = 'idfm_quota_status_';

    /** @var array<int, string> APIs dont le quota est suivi, cf. getApiQuotaStatus(). */
    private const QUOTA_SOURCES = ['navitia', 'siri'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $apiKey,
        private readonly string $apiBaseUrl,
        private readonly string $siriBaseUrl,
        private readonly GeoService $geoService,
    ) {
    }

    // ─── Suivi du quota API (F7.3) ──────────────────────────────────────────

    /**
     * La clé PRIM a un quota strict de 1000 requêtes/jour (cf. en-têtes x-ratelimit-*) : on le
     * capture à chaque appel réel pour que l'admin voie venir l'épuisement avant qu'il ne force
     * l'app entière en mode dégradé (mock), plutôt que de le découvrir après coup dans les logs.
     *
     * Navitia et SIRI sont décomptés séparément par PRIM, avec les mêmes en-têtes : les mélanger
     * dans une seule entrée ferait osciller le compteur affiché entre deux réalités différentes.
     */
    private function recordApiQuota(ResponseInterface $response, string $source): void
    {
        try {
            $headers = $response->getHeaders(false);
            $remaining = $headers['x-ratelimit-remaining-day'][0] ?? $headers['ratelimit-remaining'][0] ?? null;
            $limit = $headers['x-ratelimit-limit-day'][0] ?? $headers['ratelimit-limit'][0] ?? null;

            if (null === $remaining || null === $limit) {
                return;
            }

            $item = $this->cache->getItem(self::QUOTA_CACHE_PREFIX.$source);
            $item->set([
                'source' => $source,
                'remaining' => (int) $remaining,
                'limit' => (int) $limit,
                'checkedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('c'),
            ])->expiresAfter(86400);
            $this->cache->save($item);
        } catch (\Throwable) {
            // Le monitoring de quota ne doit jamais faire échouer un appel API réel.
        }
    }

    /**
     * Le quota le plus entamé des deux APIs : c'est celui qui basculera l'app en mode dégradé
     * en premier, donc le seul chiffre utile à qui surveille le tableau de bord.
     *
     * @return array{source?: string, remaining: int, limit: int, checkedAt: string}|null
     */
    public function getApiQuotaStatus(): ?array
    {
        $statuses = [];

        foreach (self::QUOTA_SOURCES as $source) {
            $item = $this->cache->getItem(self::QUOTA_CACHE_PREFIX.$source);
            if ($item->isHit()) {
                $statuses[] = $item->get();
            }
        }

        if ([] === $statuses) {
            return null;
        }

        usort($statuses, static fn (array $a, array $b): int => $a['remaining'] <=> $b['remaining']);

        return $statuses[0];
    }

    // ─── Stops Search ──────────────────────────────────────────────────────

    public function searchStops(string $query, ?string $type = null, int $limit = 10): array
    {
        $cacheKey = 'stops_search_'.md5($query.$type.$limit);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        // F2.2 — une requête du type "RER A", "M14", "bus 91" cherche les arrêts de la ligne
        $data = $this->isLineQuery($query) ? $this->searchLineStops($query, $limit) : [];

        if (empty($data)) {
            $data = empty($this->apiKey)
                ? $this->getMockSearchResults($query, $type, $limit)
                : $this->fetchStopsFromApi($query, $type, $limit);
        }

        $cacheItem->set($data)->expiresAfter(self::STOPS_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    // ─── Line Search (F2.2) ────────────────────────────────────────────────

    private function isLineQuery(string $query): bool
    {
        return (bool) preg_match('/^(rer|m|métro|metro|t|tram|bus|n)\s*-?\s*[a-z0-9]{1,4}$/iu', trim($query));
    }

    private function searchLineStops(string $query, int $limit): array
    {
        if (empty($this->apiKey)) {
            return $this->getMockLineStops($query, $limit);
        }

        // IDFM nomme les lignes de métro "Métro 14" : "M14" doit devenir "metro 14"
        $query = preg_replace('/^m\s*-?\s*(\d+\w*)$/i', 'metro $1', trim($query));

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl.'/pt_objects', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['q' => $query, 'type[]' => 'line', 'count' => 1],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            $line = $response->toArray()['pt_objects'][0]['line'] ?? null;
            if (null === $line) {
                return [];
            }

            $response = $this->httpClient->request('GET', $this->apiBaseUrl."/lines/{$line['id']}/stop_areas", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['count' => $limit],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            // Les stop_areas d'une ligne n'incluent pas commercial_modes :
            // on type chaque arrêt avec le mode de la ligne trouvée
            $modeName = $line['commercial_mode']['name'] ?? 'bus';
            $lineLabel = $this->formatLineLabel($modeName, $line['code'] ?? ($line['name'] ?? '?'));

            $stops = [];
            foreach ($response->toArray()['stop_areas'] ?? [] as $stopArea) {
                $stop = $this->normalizeStopArea($stopArea);
                $stop['transportType'] = $this->mapTransportType($modeName);
                $stop['lines'] = [$lineLabel];
                $stops[] = $stop;
            }

            return $stops;
        } catch (\Throwable) {
            return [];
        }
    }

    private function getMockLineStops(string $query, int $limit): array
    {
        $needle = strtoupper(str_replace(' ', '', $query));

        $filtered = array_filter($this->getAllMockStops(), function ($stop) use ($needle) {
            foreach ($stop['lines'] as $line) {
                if (str_contains(strtoupper(str_replace(' ', '', $line)), $needle)) {
                    return true;
                }
            }

            return false;
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    private function fetchStopsFromApi(string $query, ?string $type, int $limit): array
    {
        try {
            // Navitia ne filtre pas par mode de transport : on demande large puis on filtre
            $response = $this->httpClient->request('GET', $this->apiBaseUrl.'/places', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'q' => $query,
                    'type[]' => 'stop_area',
                    'count' => $type ? max($limit * 3, 30) : $limit,
                ],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            $data = $response->toArray();

            return $this->normalizePlacesResponse($data['places'] ?? [], $type, $limit);
        } catch (\Throwable) {
            return $this->getMockSearchResults($query, $type, $limit);
        }
    }

    // ─── Nearby Stops ──────────────────────────────────────────────────────

    public function getNearbyStops(float $lat, float $lon, int $radius = 500, ?string $type = null): array
    {
        $cacheKey = 'nearby_'.md5("{$lat}_{$lon}_{$radius}_{$type}");
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = empty($this->apiKey)
            ? $this->getMockNearbyStops($lat, $lon, $radius, $type)
            : $this->fetchNearbyFromApi($lat, $lon, $radius, $type);

        $cacheItem->set($data)->expiresAfter(self::STOPS_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    private function fetchNearbyFromApi(float $lat, float $lon, int $radius, ?string $type): array
    {
        try {
            $coord = rawurlencode("{$lon};{$lat}");
            $response = $this->httpClient->request('GET', $this->apiBaseUrl."/coord/{$coord}/places_nearby", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'type[]' => 'stop_area',
                    'distance' => $radius,
                    'count' => 20,
                ],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            $data = $response->toArray();

            return $this->normalizeNearbyResponse($data, $type);
        } catch (\Throwable) {
            return $this->getMockNearbyStops($lat, $lon, $radius, $type);
        }
    }

    // ─── Departures ────────────────────────────────────────────────────────

    /**
     * @param string|null $type  METRO, RER, TRAM ou BUS pour ne garder que ce mode
     * @param string|null $line  code public d'une ligne (« RER B », « M4 ») pour ne garder qu'elle
     * @param int|null    $limit nombre de départs renvoyés (par défaut DEPARTURES_DISPLAYED,
     *                           plafonné à la profondeur gardée en cache)
     */
    public function getNextDepartures(string $stopId, ?string $type = null, ?string $line = null, ?int $limit = null): array
    {
        return $this->filterDepartures($this->loadDepartures($stopId), $type, $line, $limit);
    }

    /**
     * Lignes qui desservent réellement l'arrêt, déduites de ses prochains passages : c'est ce
     * qui alimente les filtres par ligne de la page Horaires. Les déduire des départs plutôt
     * que de la fiche de l'arrêt évite de proposer une ligne dont plus rien ne part (nuit,
     * interruption) et ne coûte aucun appel : la liste complète est déjà en cache.
     *
     * @return array<int, array{lineCode: string, transportType: string}>
     */
    public function getDepartureLines(string $stopId): array
    {
        $lines = [];
        foreach ($this->loadDepartures($stopId) as $departure) {
            $code = (string) ($departure['lineCode'] ?? '');
            if ('' === $code) {
                continue;
            }
            $lines[$this->normaliserCodeLigne($code)] = [
                'lineCode' => $code,
                'transportType' => (string) ($departure['transportType'] ?? 'BUS'),
            ];
        }

        // Même ordre que les badges d'un arrêt : métro, RER, tram puis bus, chacun trié
        // naturellement (« M4 » avant « M11 », « 72 » avant « 350 »).
        $lines = array_values($lines);
        usort($lines, function (array $a, array $b): int {
            $rangA = self::MODE_PRIORITY[$a['transportType']] ?? 9;
            $rangB = self::MODE_PRIORITY[$b['transportType']] ?? 9;

            return $rangA === $rangB ? strnatcmp($a['lineCode'], $b['lineCode']) : $rangA <=> $rangB;
        });

        return $lines;
    }

    /**
     * @return array<int, array<string, mixed>> tous les prochains passages de l'arrêt, modes et
     *                                          lignes confondus
     */
    private function loadDepartures(string $stopId): array
    {
        $cacheKey = 'departures_'.md5($stopId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            $data = $cacheItem->get();
        } else {
            if (empty($this->apiKey)) {
                $data = $this->getMockDepartures($stopId);
            } else {
                // SIRI en premier : c'est la seule source qui donne l'heure réellement attendue pour
                // le métro et le bus. Navitia reste le filet de sécurité, avec ses horaires théoriques.
                $data = $this->fetchDeparturesFromSiri($stopId) ?: $this->fetchDeparturesFromApi($stopId);
            }

            $cacheItem->set($data)->expiresAfter(self::DEPARTURES_TTL);
            $this->cache->save($cacheItem);
        }

        return $data;
    }

    /**
     * Le cache retient la liste complète et le filtrage se fait à la lecture : les quatre modes
     * sont ainsi servis par un seul appel à IDFM, au lieu d'un appel par filtre. Le découpage
     * n'intervient qu'ensuite, sinon filtrer sur le RER dans un pôle à dominante bus ne
     * renverrait rien alors que des trains partent bien de cet arrêt.
     *
     * @param array<int, array<string, mixed>> $departures
     *
     * @return array<int, array<string, mixed>>
     */
    private function filterDepartures(array $departures, ?string $type, ?string $line = null, ?int $limit = null): array
    {
        if (null !== $type && '' !== $type) {
            $type = strtoupper($type);
            $departures = array_values(array_filter(
                $departures,
                static fn (array $departure): bool => ($departure['transportType'] ?? '') === $type
            ));
        }

        // « RER B », « rer b » et « rerb » désignent la même ligne : c'est ce filtre qui permet
        // de ne voir que le RER B à Gare du Nord, sans les métros ni les autres RER.
        if (null !== $line && '' !== $line) {
            $line = $this->normaliserCodeLigne($line);
            $departures = array_values(array_filter(
                $departures,
                fn (array $departure): bool => $this->normaliserCodeLigne((string) ($departure['lineCode'] ?? '')) === $line
            ));
        }

        return \array_slice($departures, 0, min(max(1, $limit ?? self::DEPARTURES_DISPLAYED), self::DEPARTURES_KEPT));
    }

    /**
     * Horaires temps réel via SIRI (stop-monitoring). Navitia, même interrogé avec
     * data_freshness=realtime, ne renvoie que du base_schedule pour le métro et le bus : ses
     * horaires sont théoriques. SIRI expose ExpectedDepartureTime, l'heure réellement attendue.
     *
     * Un seul appel couvre tout l'arrêt — toutes ses lignes et tous ses quais.
     *
     * @return array<int, array<string, mixed>> vide si SIRI est indisponible ou inexploitable,
     *                                          le repli Navitia prenant alors le relais
     */
    private function fetchDeparturesFromSiri(string $stopId): array
    {
        // stop_area:IDFM:71264 -> STIF:StopArea:SP:71264:
        if (1 !== preg_match('/^stop_area:IDFM:(\w+)$/', $stopId, $matches)) {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', $this->siriBaseUrl.'/stop-monitoring', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['MonitoringRef' => 'STIF:StopArea:SP:'.$matches[1].':'],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'siri');

            return $this->normalizeSiriDepartures($response->toArray(), $stopId);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSiriDepartures(array $data, string $stopId): array
    {
        $visits = $data['Siri']['ServiceDelivery']['StopMonitoringDelivery'][0]['MonitoredStopVisit'] ?? [];
        if (!\is_array($visits) || [] === $visits) {
            return [];
        }

        $labels = $this->getStopAreaLineLabels($stopId);
        $now = time();
        $departures = [];

        foreach ($visits as $visit) {
            $journey = $visit['MonitoredVehicleJourney'] ?? [];
            $call = $journey['MonitoredCall'] ?? [];

            // ExpectedDepartureTime = heure temps réel ; AimedDepartureTime = horaire théorique,
            // seul disponible tant que le véhicule n'est pas suivi. La distinction est reportée
            // telle quelle dans isRealtime : l'app ne doit jamais annoncer un temps réel qu'elle
            // n'a pas.
            $realtime = $call['ExpectedDepartureTime'] ?? $call['ExpectedArrivalTime'] ?? null;
            $planned = $call['AimedDepartureTime'] ?? $call['AimedArrivalTime'] ?? null;
            $time = $realtime ?? $planned;

            if (!\is_string($time) || '' === $time) {
                continue;
            }

            try {
                $departureTime = new \DateTimeImmutable($time);
            } catch (\Throwable) {
                continue;
            }

            $waitMinutes = (int) floor(($departureTime->getTimestamp() - $now) / 60);
            if ($waitMinutes < 0) {
                continue; // passage déjà effectué
            }

            // "STIF:Line::C01107:" -> "C01107"
            $lineRef = $journey['LineRef']['value'] ?? '';
            $ref = \is_string($lineRef) ? trim($lineRef, ':') : '';
            $ref = '' !== $ref ? substr($ref, (int) strrpos($ref, ':') + 1) : '';

            $line = $labels[$ref] ?? self::STRUCTURING_LINES['line:IDFM:'.$ref] ?? null;
            if (null === $line) {
                continue; // ligne non identifiable : mieux vaut l'omettre qu'afficher un code faux
            }

            $direction = $call['DestinationDisplay'][0]['value']
                ?? $journey['DestinationName'][0]['value']
                ?? $journey['DirectionName'][0]['value']
                ?? 'Terminus';

            $departures[] = [
                'lineCode' => $line[0],
                'transportType' => $line[1],
                'direction' => preg_replace('/ \([^)]*\)$/', '', $direction),
                'waitMinutes' => $waitMinutes,
                'isRealtime' => null !== $realtime,
                'platform' => null,
                '_ordre' => $departureTime->getTimestamp(),
            ];
        }

        // SIRI regroupe ses visites par ligne, pas par heure : sans ce tri, les cinq départs
        // affichés seraient ceux de la première ligne rencontrée, pas les cinq prochains.
        usort($departures, static fn (array $a, array $b): int => $a['_ordre'] <=> $b['_ordre']);

        return array_map(
            static function (array $departure): array {
                unset($departure['_ordre']);

                return $departure;
            },
            \array_slice($departures, 0, self::DEPARTURES_KEPT)
        );
    }

    /**
     * SIRI ne transporte que l'identifiant technique d'une ligne (STIF:Line::C01107:) ; son code
     * public ("72", "M4") et son mode viennent de Navitia. Un appel par arrêt, gardé 24 h : sans
     * ce cache, chaque consultation d'horaires en coûterait un second au quota.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function getStopAreaLineLabels(string $stopId): array
    {
        $cacheItem = $this->cache->getItem('stop_lines_'.md5($stopId));
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $labels = [];

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl."/stop_areas/{$stopId}/lines", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['count' => 50],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            foreach ($response->toArray()['lines'] ?? [] as $line) {
                $id = $line['id'] ?? '';
                $code = (string) ($line['code'] ?? '');
                if (!\is_string($id) || '' === $id || '' === $code) {
                    continue;
                }
                $mode = $line['commercial_mode']['name'] ?? 'bus';
                $labels[substr($id, (int) strrpos($id, ':') + 1)] = [
                    $this->formatLineLabel($mode, $code),
                    $this->mapTransportType($mode),
                ];
            }
        } catch (\Throwable) {
            // Sans libellés, seules les lignes RER/Métro de STRUCTURING_LINES resteront affichables.
        }

        $cacheItem->set($labels)->expiresAfter(self::STOP_LINES_TTL);
        $this->cache->save($cacheItem);

        return $labels;
    }

    private function fetchDeparturesFromApi(string $stopId): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl."/stop_areas/{$stopId}/departures", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    // Large : ces départs alimentent aussi le filtrage par mode.
                    'count' => 30,
                    'data_freshness' => 'realtime',
                ],
                'timeout' => 5,
            ]);
            $this->recordApiQuota($response, 'navitia');

            $data = $response->toArray();

            return $this->normalizeDeparturesResponse($data);
        } catch (\Throwable) {
            return $this->getMockDepartures($stopId);
        }
    }

    // ─── Journeys (calcul d'itinéraire) ────────────────────────────────────

    public function searchJourneys(string $fromId, string $toId, ?string $fromName = null, ?string $toName = null): array
    {
        $cacheKey = 'journeys_'.md5($fromId.'_'.$toId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = empty($this->apiKey)
            ? $this->getMockJourneys($fromId, $toId, $fromName, $toName)
            : $this->fetchJourneysFromApi($fromId, $toId, $fromName, $toName);

        $cacheItem->set($data)->expiresAfter(self::JOURNEYS_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    private function fetchJourneysFromApi(string $fromId, string $toId, ?string $fromName, ?string $toName): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl.'/journeys', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'from' => $fromId,
                    'to' => $toId,
                    'datetime' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Ymd\THis'),
                    'count' => 3,
                ],
                'timeout' => 8,
            ]);
            $this->recordApiQuota($response, 'navitia');

            $data = $response->toArray();
            $journeys = $this->normalizeJourneysResponse($data);

            return $journeys ?: $this->getMockJourneys($fromId, $toId, $fromName, $toName);
        } catch (\Throwable) {
            return $this->getMockJourneys($fromId, $toId, $fromName, $toName);
        }
    }

    private function normalizeJourneysResponse(array $data): array
    {
        $journeys = [];

        foreach ($data['journeys'] ?? [] as $journey) {
            if (($journey['status'] ?? '') === 'NO_SOLUTION') {
                continue;
            }

            $sections = [];
            foreach ($journey['sections'] ?? [] as $section) {
                if (($section['type'] ?? '') !== 'public_transport' && ($section['duration'] ?? 0) < 60) {
                    // Ignore les micro-sections de transfert/attente à pied (< 1 min), peu utiles à l'affichage
                    continue;
                }

                $info = $section['display_informations'] ?? [];
                $modeName = $info['commercial_mode'] ?? 'walking';

                $type = $section['type'] ?? 'transfer';

                $sections[] = [
                    'type' => $type,
                    // WAIT plutôt que WALK sur les sections d'attente : l'écran les annonçait
                    // « à pied », donnant un trajet à deux marches successives là où la seconde
                    // est en réalité une attente sur le quai.
                    'mode' => match ($type) {
                        'public_transport' => $this->mapTransportType($modeName),
                        'waiting' => 'WAIT',
                        default => 'WALK',
                    },
                    'lineCode' => 'public_transport' === $type
                        ? $this->formatLineLabel($modeName, $info['code'] ?? '?')
                        : null,
                    'direction' => $info['direction'] ?? null,
                    'from' => $section['from']['name'] ?? null,
                    'to' => $section['to']['name'] ?? null,
                    'departureTime' => $this->parseNavitiaDate($section['departure_date_time'] ?? null)?->format('c'),
                    'arrivalTime' => $this->parseNavitiaDate($section['arrival_date_time'] ?? null)?->format('c'),
                    'durationMinutes' => (int) round(($section['duration'] ?? 0) / 60),
                ];
            }

            $journeys[] = [
                'departureTime' => $this->parseNavitiaDate($journey['departure_date_time'] ?? null)?->format('c'),
                'arrivalTime' => $this->parseNavitiaDate($journey['arrival_date_time'] ?? null)?->format('c'),
                'durationMinutes' => (int) round(($journey['duration'] ?? 0) / 60),
                'transfers' => (int) ($journey['nb_transfers'] ?? 0),
                'sections' => $sections,
            ];
        }

        return $journeys;
    }

    private function getMockJourneys(string $fromId, string $toId, ?string $fromName = null, ?string $toName = null): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        return [
            [
                'departureTime' => $now->format('c'),
                'arrivalTime' => $now->modify('+27 minutes')->format('c'),
                'durationMinutes' => 27,
                'transfers' => 1,
                'sections' => [
                    [
                        'type' => 'public_transport',
                        'mode' => 'METRO',
                        'lineCode' => 'M1',
                        'direction' => 'La Défense',
                        'from' => $fromName ?? $fromId,
                        'to' => 'Châtelet',
                        'departureTime' => $now->format('c'),
                        'arrivalTime' => $now->modify('+14 minutes')->format('c'),
                        'durationMinutes' => 14,
                    ],
                    [
                        'type' => 'public_transport',
                        'mode' => 'RER',
                        'lineCode' => 'RER A',
                        'direction' => 'Cergy-le-Haut',
                        'from' => 'Châtelet',
                        'to' => $toName ?? $toId,
                        'departureTime' => $now->modify('+16 minutes')->format('c'),
                        'arrivalTime' => $now->modify('+27 minutes')->format('c'),
                        'durationMinutes' => 11,
                    ],
                ],
            ],
        ];
    }

    // ─── Traffic Alerts ────────────────────────────────────────────────────

    public function getTrafficAlerts(?string $lineId = null): array
    {
        $cacheKey = 'alerts_'.md5($lineId ?? 'all');
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = empty($this->apiKey)
            ? $this->getMockAlerts($lineId)
            : $this->fetchAlertsFromApi($lineId);

        $cacheItem->set($data)->expiresAfter(self::ALERTS_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    private function fetchAlertsFromApi(?string $lineId): array
    {
        if (null !== $lineId) {
            $disruptions = $this->fetchPaginatedDisruptions("/lines/{$lineId}/line_reports");
            if (isset(self::STRUCTURING_LINES[$lineId])) {
                [$lineCode, $transportType] = self::STRUCTURING_LINES[$lineId];
                foreach ($disruptions as &$disruption) {
                    $disruption['_knownLineCode'] = $lineCode;
                    $disruption['_knownTransportType'] = $transportType;
                }
                unset($disruption);
            }

            return $disruptions ? $this->normalizeAlertsResponse(['disruptions' => $disruptions]) : $this->getMockAlerts($lineId);
        }

        // Le flux global /disruptions contient ~5000 perturbations pour tout le réseau (dont
        // une majorité de bus et d'incidents très locaux), sans tri par pertinence : se limiter
        // à un échantillon plafonné en ferait passer certaines à la trappe (ex. une coupure RER
        // majeure arrivant après la 300e position). Le réseau structurant (RER/Métro/Tram/Transilien)
        // est donc récupéré ligne par ligne — exhaustif et rapide — puis complété par un échantillon
        // du flux global pour le reste (essentiellement le réseau bus).
        $structuring = $this->fetchStructuringNetworkDisruptions();
        $globalSample = $this->fetchPaginatedDisruptions('/disruptions');

        $unique = [];
        foreach ([...$structuring, ...$globalSample] as $disruption) {
            $unique[$disruption['id'] ?? uniqid('', true)] = $disruption;
        }

        return $unique ? $this->normalizeAlertsResponse(['disruptions' => array_values($unique)]) : $this->getMockAlerts();
    }

    /** Requêtes concurrentes (multiplexées par curl) sur chaque ligne structurante. */
    private function fetchStructuringNetworkDisruptions(): array
    {
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Ymd\THis');

        $requests = [];
        foreach (self::STRUCTURING_LINES as $lineId => $meta) {
            try {
                $requests[] = [
                    'meta' => $meta,
                    'response' => $this->httpClient->request('GET', $this->apiBaseUrl."/lines/{$lineId}/line_reports", [
                        'headers' => ['apikey' => $this->apiKey],
                        'query' => ['count' => 100, 'since' => $since],
                        'timeout' => 5,
                    ]),
                ];
            } catch (\Throwable) {
                // Une ligne indisponible ne doit pas faire échouer les autres.
            }
        }

        $disruptions = [];
        $quotaRecorded = false;
        foreach ($requests as $request) {
            [$lineCode, $transportType] = $request['meta'];
            try {
                if (!$quotaRecorded) {
                    // Un seul enregistrement suffit : les 21 requêtes concurrentes reflètent
                    // quasiment le même état de quota, inutile d'écrire 21 fois dans le cache.
                    $this->recordApiQuota($request['response'], 'navitia');
                    $quotaRecorded = true;
                }
                foreach ($request['response']->toArray()['disruptions'] ?? [] as $disruption) {
                    // On sait déjà à quelle ligne appartient cette perturbation (on l'a demandée
                    // explicitement) : inutile de deviner via extractImpactedLine().
                    $disruption['_knownLineCode'] = $lineCode;
                    $disruption['_knownTransportType'] = $transportType;
                    $disruptions[] = $disruption;
                }
            } catch (\Throwable) {
                // Une ligne indisponible ne doit pas faire échouer les autres.
            }
        }

        return $disruptions;
    }

    private function fetchPaginatedDisruptions(string $path): array
    {
        $since = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Ymd\THis');
        $disruptions = [];

        try {
            // L'API PRIM (Navitia) pagine ses résultats : on parcourt les pages
            // tant qu'il en reste, avec un garde-fou pour éviter une boucle trop longue.
            for ($page = 0; $page < self::ALERTS_MAX_PAGES; ++$page) {
                $response = $this->httpClient->request('GET', $this->apiBaseUrl.$path, [
                    'headers' => ['apikey' => $this->apiKey],
                    'query' => [
                        'count' => self::ALERTS_PAGE_SIZE,
                        'start_page' => $page,
                        'since' => $since,
                    ],
                    'timeout' => 5,
                ]);
                $this->recordApiQuota($response, 'navitia');

                $data = $response->toArray();
                $pageDisruptions = $data['disruptions'] ?? [];
                $disruptions = array_merge($disruptions, $pageDisruptions);

                $pagination = $data['pagination'] ?? [];
                $itemsOnPage = (int) ($pagination['items_on_page'] ?? count($pageDisruptions));
                $totalResult = (int) ($pagination['total_result'] ?? count($disruptions));

                if ($itemsOnPage < self::ALERTS_PAGE_SIZE || count($disruptions) >= $totalResult) {
                    break;
                }
            }
                // Heure de passage en clair : « départ à 8h12 » reste juste quand la page est
                // restée ouverte, là où le nombre de minutes vieillit avec le cache.
                'departureTime' => $departureTime->format(DATE_ATOM),
        } catch (\Throwable) {
            // On garde ce qui a déjà été récupéré avant l'échec.
        }

        return $disruptions;
    }

    // ─── Response Normalizers ──────────────────────────────────────────────

    private function normalizePlacesResponse(array $places, ?string $type, int $limit): array
    {
        $stops = [];
        foreach ($places as $place) {
            if (($place['embedded_type'] ?? '') !== 'stop_area') {
                continue;
            }
            $stop = $this->normalizeStopArea($place['stop_area'] ?? []);
            if ($type && $stop['transportType'] !== strtoupper($type)) {
                continue;
            }
            $stops[] = $stop;
            if (count($stops) >= $limit) {
                break;
            }
        }

        return $stops;
    }

    private function normalizeNearbyResponse(array $data, ?string $type): array
    {
        $stops = [];
        foreach ($data['places_nearby'] ?? [] as $place) {
            if (($place['embedded_type'] ?? '') !== 'stop_area') {
                continue;
            }
            $stop = $this->normalizeStopArea($place['stop_area'] ?? []);
            if ($type && $stop['transportType'] !== strtoupper($type)) {
                continue;
            }
            // IDFM renvoie une distance à vol d'oiseau (Navitia) : F6.4 demande une distance
            // à pied, on applique le facteur de détour urbain plutôt que d'afficher le brut.
            $dist = (int) round($this->geoService->applyWalkingDetourFactor((float) ($place['distance'] ?? 0)));
            $stop['distance'] = $dist;
            $stop['distanceLabel'] = $this->geoService->formatDistance($dist);
            $stops[] = $stop;
        }

        usort($stops, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice($stops, 0, 10);
    }

    private function normalizeStopArea(array $stopArea): array
    {
        $modeName = $this->pickPrimaryMode($stopArea['commercial_modes'] ?? []);

        // Navitia liste les lignes dans un ordre qui lui est propre, bus compris : à La Défense,
        // ses 27 lignes commencent par le M1 puis huit lignes de bus, si bien qu'une simple
        // troncature faisait disparaître le RER A, le RER E, le T2 et les Transilien. On trie
        // donc par importance de mode avant de couper, pour que l'arrêt s'annonce par ce qui le
        // caractérise — et que ses badges correspondent aux départs affichés juste après.
        $lignes = [];
        foreach ($stopArea['lines'] ?? [] as $line) {
            $lineMode = $line['commercial_mode']['name'] ?? 'bus';
            $label = $this->formatLineLabel($lineMode, $line['code'] ?? ($line['name'] ?? '?'));
            $rang = self::MODE_PRIORITY[$this->mapTransportType($lineMode)] ?? 9;

            $lignes[] = [
                'label' => $label,
                // Le Transilien partage le rang du RER (mapTransportType les confond) mais passe
                // après lui : "M1, RER A, RER E, L, U" se lit mieux que "M1, L, RER A, RER E, U".
                'rang' => 10 * $rang + (str_starts_with($label, 'RER ') ? 0 : 1),
            ];
        }

        usort($lignes, static function (array $a, array $b): int {
            return $a['rang'] === $b['rang']
                ? strnatcmp($a['label'], $b['label'])
                : $a['rang'] <=> $b['rang'];
        });

        // Une même lettre peut arriver deux fois (Transilien L et bus L à La Défense).
        $lines = \array_slice(array_values(array_unique(array_column($lignes, 'label'))), 0, 8);

        return [
            'id' => $stopArea['id'] ?? uniqid(),
            'name' => $stopArea['name'] ?? 'Arrêt inconnu',
            'lat' => (float) ($stopArea['coord']['lat'] ?? 48.8566),
            'lon' => (float) ($stopArea['coord']['lon'] ?? 2.3522),
            'transportType' => $this->mapTransportType($modeName),
            'lines' => $lines,
        ];
    }

    private function normalizeDeparturesResponse(array $data): array
    {
        $departures = [];
        foreach ($data['departures'] ?? [] as $dep) {
            $info = $dep['display_informations'] ?? [];
            $stopDateTime = $dep['stop_date_time'] ?? [];

            $departureTime = $this->parseNavitiaDate($stopDateTime['departure_date_time'] ?? null);
            if (null === $departureTime) {
                continue;
            }
            $waitMinutes = (int) floor(($departureTime->getTimestamp() - time()) / 60);

            $modeName = $info['commercial_mode'] ?? ($dep['route']['line']['commercial_mode']['name'] ?? 'bus');
            $code = $info['code'] ?? ($dep['route']['line']['code'] ?? '?');
            // "Aéroport d'Orly (Paray-Vieille-Poste)" → "Aéroport d'Orly"
            $direction = preg_replace('/ \([^)]*\)$/', '', $info['direction'] ?? ($dep['route']['name'] ?? 'Terminus'));

            $departures[] = [
                'lineCode' => $this->formatLineLabel($modeName, $code),
                'transportType' => $this->mapTransportType($modeName),
                'direction' => $direction,
                'waitMinutes' => max(0, $waitMinutes),
                'isRealtime' => ($stopDateTime['data_freshness'] ?? '') === 'realtime',
                'platform' => $dep['stop_point']['platform_code'] ?? null,
            ];
        }

        return \array_slice($departures, 0, self::DEPARTURES_KEPT);
    }

    private function normalizeAlertsResponse(array $data): array
    {
        $cutoff = new \DateTimeImmutable(self::ALERTS_MAX_AGE, new \DateTimeZone('Europe/Paris'));
        $alerts = [];

        foreach ($data['disruptions'] ?? [] as $disruption) {
            if (($disruption['status'] ?? 'active') === 'past') {
                continue;
            }

            $startDate = $this->parseNavitiaDate($disruption['application_periods'][0]['begin'] ?? null);

            // Ignore les notices permanentes trop anciennes (ex. infos datant de plusieurs années)
            if (null !== $startDate && $startDate < $cutoff) {
                continue;
            }

            $categoryText = strtolower(implode(' ', array_filter([
                $disruption['cause'] ?? '',
                $disruption['category'] ?? '',
                implode(' ', $disruption['tags'] ?? []),
            ])));

            $title = $this->extractMessage($disruption['messages'] ?? [], 'title') ?? 'Perturbation en cours';

            // Perturbation récupérée via une ligne structurante connue (cf. STRUCTURING_LINES) :
            // pas besoin de deviner, on sait déjà à quelle ligne elle appartient.
            if (isset($disruption['_knownLineCode'])) {
                $lineCode = $disruption['_knownLineCode'];
                $transportType = $disruption['_knownTransportType'];
            } else {
                [$lineCode, $transportType] = $this->extractImpactedLine($disruption['impacted_objects'] ?? [], $title);
            }

            $endDate = $this->parseNavitiaDate($disruption['application_periods'][0]['end'] ?? null);

            $alerts[] = [
                'id' => $disruption['id'] ?? uniqid(),
                'lineCode' => $lineCode,
                'transportType' => $transportType,
                'severity' => $this->mapSeverity($disruption['severity']['effect'] ?? ($disruption['severity']['name'] ?? 'INFO')),
                'category' => $this->mapCategory($categoryText),
                'title' => $title,
                'description' => $this->extractMessage($disruption['messages'] ?? [], 'web') ?? '',
                'estimatedResume' => null,
                'startDate' => $startDate?->format('c') ?? date('c'),
                'endDate' => $endDate?->format('c'),
                // updated_at = dernière modification par IDFM : sert à faire remonter les infos les plus fraîches
                'sortKey' => $this->parseNavitiaDate($disruption['updated_at'] ?? null)?->getTimestamp() ?? ($startDate?->getTimestamp() ?? 0),
                '_startTs' => $startDate?->getTimestamp() ?? 0,
                '_endTs' => $endDate?->getTimestamp(),
            ];
        }

        $alerts = $this->mergeRecurringAlerts($alerts);

        // Les plus graves d'abord (une coupure de ligne avant une panne d'ascenseur isolée),
        // puis les plus récemment mises à jour au sein d'un même niveau de gravité.
        $severityRank = ['MAJOR' => 0, 'MODERATE' => 1, 'INFO' => 2];
        usort($alerts, function (array $a, array $b) use ($severityRank): int {
            $rankDiff = ($severityRank[$a['severity']] ?? 3) <=> ($severityRank[$b['severity']] ?? 3);

            return 0 !== $rankDiff ? $rankDiff : $b['sortKey'] <=> $a['sortKey'];
        });

        return array_map(function (array $alert): array {
            unset($alert['sortKey'], $alert['_startTs'], $alert['_endTs']);

            return $alert;
        }, $alerts);
    }

    /**
     * IDFM segmente une même perturbation récurrente (ex. travaux estivaux) en une entrée par
     * occurrence quotidienne — jusqu'à plusieurs dizaines pour une fermeture de plusieurs semaines.
     * On les fusionne (même ligne/titre/description) en une seule alerte couvrant toute la période,
     * pour éviter de noyer la liste et de sous-évaluer l'ampleur réelle de l'événement.
     */
    private function mergeRecurringAlerts(array $alerts): array
    {
        $grouped = [];

        foreach ($alerts as $alert) {
            $key = $alert['lineCode'].'|'.$alert['title'].'|'.$alert['description'];

            if (!isset($grouped[$key])) {
                $grouped[$key] = $alert;
                continue;
            }

            $existing = $grouped[$key];
            if ($alert['_startTs'] < $existing['_startTs']) {
                $existing['startDate'] = $alert['startDate'];
                $existing['_startTs'] = $alert['_startTs'];
            }
            if (null !== $alert['_endTs'] && (null === $existing['_endTs'] || $alert['_endTs'] > $existing['_endTs'])) {
                $existing['endDate'] = $alert['endDate'];
                $existing['_endTs'] = $alert['_endTs'];
            }
            if ($alert['sortKey'] > $existing['sortKey']) {
                $existing['sortKey'] = $alert['sortKey'];
            }
            $grouped[$key] = $existing;
        }

        return array_values($grouped);
    }

    /** @return array{0: string, 1: string} [lineCode, transportType] */
    private function extractImpactedLine(array $impactedObjects, string $title = ''): array
    {
        foreach ($impactedObjects as $object) {
            $ptObject = $object['pt_object'] ?? [];
            if (($ptObject['embedded_type'] ?? '') !== 'line') {
                continue;
            }
            $line = $ptObject['line'] ?? [];
            $modeName = $line['commercial_mode']['name'] ?? 'metro';
            $code = $line['code'] ?? ($ptObject['name'] ?? 'Inconnue');

            return [$this->formatLineLabel($modeName, $code), $this->mapTransportType($modeName)];
        }

        // Beaucoup de perturbations bus/train ne référencent qu'un arrêt (pas de "line" exploitable),
        // mais le titre suit un format stable ("Bus 66 : ...", "RER B : ...", "Ligne N : ...").
        if (preg_match('/^(Bus|RER|Ligne|Tramway|Tram)\s+([A-Za-z0-9]+)/ui', trim($title), $matches)) {
            $prefix = strtolower($matches[1]);
            $code = $matches[2];

            return match ($prefix) {
                'bus' => [$code, 'BUS'],
                'rer' => ["RER {$code}", 'RER'],
                'tram', 'tramway' => [$code, 'TRAM'],
                default => [$code, 'RER'], // "Ligne X" désigne un Transilien (H, N, P...)
            };
        }

        return [$impactedObjects[0]['pt_object']['name'] ?? 'Inconnue', 'METRO'];
    }

    private function extractMessage(array $messages, string $channelType): ?string
    {
        foreach ($messages as $message) {
            if (in_array($channelType, $message['channel']['types'] ?? [], true)) {
                return $this->cleanMessageText($message['text'] ?? '') ?: null;
            }
        }

        // Fallback : premier message disponible (utile pour le titre)
        if ('title' === $channelType && isset($messages[0]['text'])) {
            return $this->cleanMessageText($messages[0]['text']) ?: null;
        }

        return null;
    }

    /** Les messages IDFM sont en HTML : entités ("&#233;", "&nbsp;") à décoder après suppression des balises. */
    private function cleanMessageText(string $text): string
    {
        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function parseNavitiaDate(?string $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Ymd\THis', $value, new \DateTimeZone('Europe/Paris'));

        return $date ?: null;
    }

    /** Un arrêt multi-modes (ex. Châtelet) est typé selon son mode le plus structurant. */
    private function pickPrimaryMode(array $commercialModes): string
    {
        $priority = self::MODE_PRIORITY;
        $best = 'bus';
        $bestRank = PHP_INT_MAX;

        foreach ($commercialModes as $mode) {
            $name = $mode['name'] ?? '';
            $rank = $priority[$this->mapTransportType($name)] ?? PHP_INT_MAX;
            if ($rank < $bestRank) {
                $best = $name;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    private function formatLineLabel(string $modeName, string $code): string
    {
        // Le Transilien (H, J, K, L, N, P, R, U, V) et le TER sont assimilés au type RER pour le
        // filtrage, mais gardent leur code brut : "RER J" n'existe pas, ces lignes s'appellent
        // juste "J", et un TER reste un "TER".
        if (in_array(strtolower($modeName), ['train transilien', 'transilien', 'ter', 'longdistancetrain', 'long distance train'], true)) {
            return $code;
        }

        return match ($this->mapTransportType($modeName)) {
            'METRO' => 'M'.$code,
            'RER' => 'RER '.$code,
            default => $code,
        };
    }

    private function mapTransportType(string $mode): string
    {
        return match (strtolower($mode)) {
            'metro', 'métro', 'subway' => 'METRO',
            'rer', 'rail', 'train', 'rapid_transit', 'rapidtransit', 'localtrain', 'local train',
            // Le TER dessert quelques gares franciliennes (Versailles Chantiers, Mantes) : sans
            // lui, un train régional s'affichait avec la couleur et l'icône du bus.
            'train transilien', 'transilien', 'ter', 'longdistancetrain', 'long distance train' => 'RER',
            'tram', 'tramway', 'tram_train' => 'TRAM',
            default => 'BUS',
        };
    }

    private function mapSeverity(string $severity): string
    {
        return match (strtolower($severity)) {
            'blocking', 'no_service' => 'MAJOR',
            'reduced_service', 'significant_delays', 'detour', 'modified_service' => 'MODERATE',
            default => 'INFO',
        };
    }

    private function mapCategory(string $cause): string
    {
        $travauxKeywords = ['travaux', 'maintenance', 'modernisation', 'rénovation', 'works', 'construction'];

        foreach ($travauxKeywords as $keyword) {
            if (str_contains($cause, $keyword)) {
                return 'TRAVAUX';
            }
        }

        return 'INCIDENT';
    }

    // ─── Mock Data (demo / API key not configured) ─────────────────────────

    /**
     * Minuscules sans accents, pour comparer une saisie clavier à un nom d'arrêt : sans ça,
     * « chatelet » ne trouve pas « Châtelet », alors que personne ne tape les accents dans une
     * barre de recherche. Seul le mode simulé en a besoin — interrogée avec une clé, l'API PRIM
     * fait déjà cette correspondance de son côté.
     */
    private function normaliserLibelle(string $texte): string
    {
        return strtr(mb_strtolower($texte, 'UTF-8'), self::EQUIVALENCES_ACCENTS);
    }

    private function getMockSearchResults(string $query, ?string $type, int $limit): array
    {
        $allStops = $this->getAllMockStops();
        $query = $this->normaliserLibelle($query);

        $filtered = array_filter($allStops, function ($stop) use ($query, $type) {
            $nameMatch = str_contains($this->normaliserLibelle($stop['name']), $query);
            $typeMatch = !$type || $stop['transportType'] === strtoupper($type);

            return $nameMatch && $typeMatch;
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    private function getMockNearbyStops(float $lat, float $lon, int $radius, ?string $type): array
    {
        $stops = $this->getAllMockStops();

        // Filter by type
        if ($type) {
            $stops = array_filter($stops, fn ($s) => $s['transportType'] === strtoupper($type));
        }

        // F6.4 — distance de marche estimée (GeoService : vol d'oiseau + facteur de détour urbain),
        // pas la distance à vol d'oiseau brute.
        foreach ($stops as &$stop) {
            $dist = (int) round($this->geoService->estimateWalkingDistance($lat, $lon, $stop['lat'], $stop['lon']));
            $stop['distance'] = $dist;
            $stop['distanceLabel'] = $this->geoService->formatDistance($dist);
        }
        unset($stop);

        // Le rayon demandé doit être respecté : sans ce filtre, "arrêts à proximité" pouvait
        // renvoyer les 10 arrêts les plus proches du jeu de données mock même à 30km de distance.
        $stops = array_filter($stops, fn ($s) => $s['distance'] <= $radius);

        // Sort by distance
        usort($stops, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice(array_values($stops), 0, 10);
    }

    private function getMockDepartures(string $stopId): array
    {
        $lines = $this->getMockLinesForStop($stopId);
        $departures = [];

        foreach ($lines as $line) {
            $base = random_int(1, 4);
            for ($i = 0; $i < 3; ++$i) {
                $wait = $base + ($i * random_int(4, 7));
                $departures[] = [
                    'lineCode' => $line['code'],
                    'transportType' => $line['type'],
                    'direction' => $line['direction'],
                    'waitMinutes' => $wait,
                    // Jamais true : ces horaires sont fabriqués, les annoncer en temps réel
                    // ferait passer une panne de l'API IDFM pour de la donnée vérifiée.
                    'isRealtime' => false,
                    'platform' => null,
                ];
            }
        }

        // Trie comme les vraies sources : sans cela le repli listerait ligne par ligne.
        usort($departures, static fn (array $a, array $b): int => $a['waitMinutes'] <=> $b['waitMinutes']);

        return $departures;
    }

    /**
     * @param string|null $lineId identifiant IDFM (« line:IDFM:C01384 ») ou code affiché (« M14 »)
     *                            — l'appelant public utilise le second, la vraie API le premier
     */
    private function getMockAlerts(?string $lineId = null): array
    {
        $alertes = [
            [
                'id' => 'alert-1',
                'lineCode' => 'M1',
                'transportType' => 'METRO',
                'severity' => 'MAJOR',
                'category' => 'INCIDENT',
                'title' => 'Trafic interrompu entre Vincennes et Nation',
                'description' => 'Suite à un incident technique, le trafic est interrompu sur la ligne 1 entre Vincennes et Nation. Des bus de substitution sont mis en place.',
                'estimatedResume' => '15h30',
                'startDate' => date('c', strtotime('-2 hours')),
                'endDate' => null,
            ],
            [
                'id' => 'alert-2',
                'lineCode' => 'RER B',
                'transportType' => 'RER',
                'severity' => 'MODERATE',
                'category' => 'INCIDENT',
                'departureTime' => $departureTime->format(DATE_ATOM),
                'title' => 'Ralentissements +5-10 min sur le RER B',
                'description' => 'Suite à un incident technique à Gare du Nord, des ralentissements de 5 à 10 minutes sont à prévoir.',
                'estimatedResume' => null,
                'startDate' => date('c', strtotime('-1 hour')),
                'endDate' => null,
            ],
            [
                'id' => 'alert-3',
                'lineCode' => 'T7',
                'transportType' => 'TRAM',
                'severity' => 'INFO',
                'category' => 'TRAVAUX',
                'title' => 'Travaux de maintenance — Bus de remplacement ce week-end (T7)',
                'description' => 'Des travaux de maintenance sont prévus ce week-end. Des bus de remplacement circuleront entre Villejuif et Athis-Mons.',
                'estimatedResume' => null,
                'startDate' => date('c'),
                'endDate' => date('c', strtotime('+2 days')),
            ],
            [
                'id' => 'alert-4',
                'lineCode' => 'M14',
                'transportType' => 'METRO',
                'severity' => 'INFO',
                'category' => 'TRAVAUX',
                'title' => 'Travaux de modernisation — Fermeture partielle M14',
                'description' => 'Dans le cadre des travaux de modernisation de la ligne 14, la station Olympiades sera fermée du 5 au 12 avril.',
                'estimatedResume' => null,
                'startDate' => date('c', strtotime('+5 days')),
                'endDate' => date('c', strtotime('+12 days')),
            ],
        ];

        if (null === $lineId) {
            return $alertes;
        }

        // Sans ce filtre, demander les alertes de M14 renvoyait celles de tout le réseau : la
        // branche avec clé API, elle, interroge /lines/{id}/line_reports et ne remonte donc que
        // la ligne demandée. Les deux modes doivent répondre à la même question.
        $code = self::STRUCTURING_LINES[$lineId][0] ?? $lineId;
        $code = $this->normaliserCodeLigne($code);

        return array_values(array_filter(
            $alertes,
            fn (array $alerte) => $this->normaliserCodeLigne($alerte['lineCode']) === $code
        ));
    }

    /** « RER B », « rer b » et « rerb » désignent la même ligne. */
    private function normaliserCodeLigne(string $code): string
    {
        return str_replace([' ', '-'], '', mb_strtolower($code, 'UTF-8'));
    }

    private function getAllMockStops(): array
    {
        return [
            // ─── Grands pôles déjà présents ────────────────────────────────
            ['id' => 'stop:M14:chatelet', 'name' => 'Châtelet', 'lat' => 48.8596, 'lon' => 2.3473, 'transportType' => 'METRO', 'lines' => ['M1', 'M4', 'M7', 'M11', 'M14']],
            ['id' => 'stop:RERA:gare_nord', 'name' => 'Gare du Nord', 'lat' => 48.8809, 'lon' => 2.3553, 'transportType' => 'RER', 'lines' => ['RER B', 'RER D', 'RER E']],
            ['id' => 'stop:M1:nation', 'name' => 'Nation', 'lat' => 48.8484, 'lon' => 2.3960, 'transportType' => 'METRO', 'lines' => ['M1', 'M2', 'M6', 'M9']],
            ['id' => 'stop:RERA:defense', 'name' => 'La Défense', 'lat' => 48.8921, 'lon' => 2.2391, 'transportType' => 'RER', 'lines' => ['RER A', 'M1']],
            ['id' => 'stop:M14:saint_lazare', 'name' => 'Saint-Lazare', 'lat' => 48.8750, 'lon' => 2.3250, 'transportType' => 'METRO', 'lines' => ['M3', 'M12', 'M13', 'M14']],
            ['id' => 'stop:T3b:pont_bondy', 'name' => 'Pont de Bondy', 'lat' => 48.9083, 'lon' => 2.4250, 'transportType' => 'TRAM', 'lines' => ['T3b']],
            ['id' => 'stop:BUS91:montparnasse', 'name' => 'Montparnasse', 'lat' => 48.8422, 'lon' => 2.3219, 'transportType' => 'BUS', 'lines' => ['91', '96']],
            ['id' => 'stop:RERB:cergy', 'name' => 'Cergy-le-Haut', 'lat' => 49.0360, 'lon' => 2.0440, 'transportType' => 'RER', 'lines' => ['RER A']],
            ['id' => 'stop:M14:gare_lyon', 'name' => 'Gare de Lyon', 'lat' => 48.8445, 'lon' => 2.3735, 'transportType' => 'METRO', 'lines' => ['M1', 'M14']],
            ['id' => 'stop:M6:trocadero', 'name' => 'Trocadéro', 'lat' => 48.8635, 'lon' => 2.2886, 'transportType' => 'METRO', 'lines' => ['M6', 'M9']],
            ['id' => 'stop:RERC:versailles', 'name' => 'Versailles Chantiers', 'lat' => 48.8001, 'lon' => 2.1301, 'transportType' => 'RER', 'lines' => ['RER C']],
            ['id' => 'stop:T3a:porte_italie', 'name' => 'Porte d\'Italie', 'lat' => 48.8193, 'lon' => 2.3623, 'transportType' => 'TRAM', 'lines' => ['T3a']],

            // ─── Paris intra-muros — métro ─────────────────────────────────
            ['id' => 'stop:M5:bastille', 'name' => 'Bastille', 'lat' => 48.8531, 'lon' => 2.3692, 'transportType' => 'METRO', 'lines' => ['M1', 'M5', 'M8']],
            ['id' => 'stop:M3:republique', 'name' => 'République', 'lat' => 48.8674, 'lon' => 2.3634, 'transportType' => 'METRO', 'lines' => ['M3', 'M5', 'M8', 'M9', 'M11']],
            ['id' => 'stop:M3:opera', 'name' => 'Opéra', 'lat' => 48.8708, 'lon' => 2.3316, 'transportType' => 'METRO', 'lines' => ['M3', 'M7', 'M8']],
            ['id' => 'stop:M4:gare_est', 'name' => 'Gare de l\'Est', 'lat' => 48.8763, 'lon' => 2.3590, 'transportType' => 'METRO', 'lines' => ['M4', 'M5', 'M7']],
            ['id' => 'stop:M4:denfert', 'name' => 'Denfert-Rochereau', 'lat' => 48.8339, 'lon' => 2.3327, 'transportType' => 'RER', 'lines' => ['RER B', 'M4', 'M6']],
            ['id' => 'stop:M6:italie', 'name' => 'Place d\'Italie', 'lat' => 48.8313, 'lon' => 2.3554, 'transportType' => 'METRO', 'lines' => ['M5', 'M6', 'M7']],
            ['id' => 'stop:M14:bercy', 'name' => 'Bercy', 'lat' => 48.8395, 'lon' => 2.3789, 'transportType' => 'METRO', 'lines' => ['M6', 'M14']],
            ['id' => 'stop:M2:dauphine', 'name' => 'Porte Dauphine', 'lat' => 48.8715, 'lon' => 2.2755, 'transportType' => 'METRO', 'lines' => ['M2']],
            ['id' => 'stop:M8:creteil', 'name' => 'Créteil-Préfecture', 'lat' => 48.7776, 'lon' => 2.4548, 'transportType' => 'METRO', 'lines' => ['M8']],
            ['id' => 'stop:M9:montreuil', 'name' => 'Mairie de Montreuil', 'lat' => 48.8636, 'lon' => 2.4425, 'transportType' => 'METRO', 'lines' => ['M9']],
            ['id' => 'stop:M7:villejuif', 'name' => 'Villejuif-Louis Aragon', 'lat' => 48.7936, 'lon' => 2.3733, 'transportType' => 'METRO', 'lines' => ['M7']],

            // ─── RER / Transilien ───────────────────────────────────────────
            ['id' => 'stop:RERA:chatelet_les_halles', 'name' => 'Châtelet - Les Halles', 'lat' => 48.8617, 'lon' => 2.3470, 'transportType' => 'RER', 'lines' => ['RER A', 'RER B', 'RER D']],
            ['id' => 'stop:RERB:luxembourg', 'name' => 'Luxembourg', 'lat' => 48.8462, 'lon' => 2.3400, 'transportType' => 'RER', 'lines' => ['RER B']],
            ['id' => 'stop:RERB:antony', 'name' => 'Antony', 'lat' => 48.7538, 'lon' => 2.2967, 'transportType' => 'RER', 'lines' => ['RER B']],
            ['id' => 'stop:RERB:massy', 'name' => 'Massy-Palaiseau', 'lat' => 48.7263, 'lon' => 2.2593, 'transportType' => 'RER', 'lines' => ['RER B', 'RER C']],
            ['id' => 'stop:RERA:noisy', 'name' => 'Noisy-le-Grand-Mont d\'Est', 'lat' => 48.8443, 'lon' => 2.5537, 'transportType' => 'RER', 'lines' => ['RER A']],
            ['id' => 'stop:RERA:marne_la_vallee', 'name' => 'Marne-la-Vallée - Chessy', 'lat' => 48.8695, 'lon' => 2.7828, 'transportType' => 'RER', 'lines' => ['RER A']],
            ['id' => 'stop:RERA:vincennes', 'name' => 'Vincennes', 'lat' => 48.8471, 'lon' => 2.4372, 'transportType' => 'RER', 'lines' => ['RER A']],
            ['id' => 'stop:RERA:poissy', 'name' => 'Poissy', 'lat' => 48.9295, 'lon' => 2.0398, 'transportType' => 'RER', 'lines' => ['RER A']],
            ['id' => 'stop:RERB:aulnay', 'name' => 'Aulnay-sous-Bois', 'lat' => 48.9333, 'lon' => 2.4919, 'transportType' => 'RER', 'lines' => ['RER B']],
            ['id' => 'stop:RERD:juvisy', 'name' => 'Juvisy', 'lat' => 48.6928, 'lon' => 2.3785, 'transportType' => 'RER', 'lines' => ['RER C', 'RER D']],
            ['id' => 'stop:RERD:melun', 'name' => 'Melun', 'lat' => 48.5386, 'lon' => 2.6598, 'transportType' => 'RER', 'lines' => ['RER D']],
            ['id' => 'stop:RERB:roissy', 'name' => 'Aéroport CDG 1', 'lat' => 49.0007, 'lon' => 2.5732, 'transportType' => 'RER', 'lines' => ['RER B']],

            // ─── Tramway ────────────────────────────────────────────────────
            ['id' => 'stop:T1:saint_denis', 'name' => 'Marché de Saint-Denis', 'lat' => 48.9356, 'lon' => 2.3573, 'transportType' => 'TRAM', 'lines' => ['T1']],
            ['id' => 'stop:T2:issy', 'name' => 'Issy-Val de Seine', 'lat' => 48.8265, 'lon' => 2.2695, 'transportType' => 'TRAM', 'lines' => ['T2', 'RER C']],
            ['id' => 'stop:T3a:vincennes_porte', 'name' => 'Porte de Vincennes', 'lat' => 48.8467, 'lon' => 2.4098, 'transportType' => 'TRAM', 'lines' => ['T3a']],
            ['id' => 'stop:T7:athis_mons', 'name' => 'Athis-Mons', 'lat' => 48.7113, 'lon' => 2.3893, 'transportType' => 'TRAM', 'lines' => ['T7']],

            // ─── Bus ────────────────────────────────────────────────────────
            ['id' => 'stop:BUS38:luxembourg', 'name' => 'Gay-Lussac', 'lat' => 48.8443, 'lon' => 2.3419, 'transportType' => 'BUS', 'lines' => ['38', '82']],
            ['id' => 'stop:BUS72:hotel_de_ville', 'name' => 'Hôtel de Ville', 'lat' => 48.8566, 'lon' => 2.3522, 'transportType' => 'BUS', 'lines' => ['72', '74', '76']],
        ];
    }

    private function getMockLinesForStop(string $stopId): array
    {
        $map = [
            'stop:M14:saint_lazare' => [
                ['code' => 'M14', 'type' => 'METRO', 'direction' => 'Saint-Denis Pleyel'],
                ['code' => 'M3', 'type' => 'METRO', 'direction' => 'Gallieni'],
            ],
            'stop:RERA:gare_nord' => [
                ['code' => 'RER B', 'type' => 'RER', 'direction' => 'Mitry-Claye'],
                ['code' => 'RER D', 'type' => 'RER', 'direction' => 'Melun'],
            ],
            'stop:T3b:pont_bondy' => [
                ['code' => 'T3b', 'type' => 'TRAM', 'direction' => 'Porte de la Chapelle'],
            ],
        ];

        return $map[$stopId] ?? [
            ['code' => 'M1', 'type' => 'METRO', 'direction' => 'La Défense'],
        ];
    }
}
                    'departureTime' => (new \DateTimeImmutable("+{$wait} minutes"))->format(DATE_ATOM),

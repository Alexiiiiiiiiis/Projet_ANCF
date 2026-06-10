<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class IdfmApiService
{
    private const DEPARTURES_TTL = 30;   // seconds
    private const STOPS_TTL = 300;       // 5 minutes
    private const ALERTS_TTL = 120;      // 2 minutes

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheItemPoolInterface $cache,
        private readonly string $apiKey,
        private readonly string $apiBaseUrl,
    ) {}

    // ─── Stops Search ──────────────────────────────────────────────────────

    public function searchStops(string $query, ?string $type = null, int $limit = 10): array
    {
        $cacheKey = 'stops_search_' . md5($query . $type . $limit);
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
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/pt_objects', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['q' => $query, 'type[]' => 'line', 'count' => 1],
                'timeout' => 5,
            ]);

            $line = $response->toArray()['pt_objects'][0]['line'] ?? null;
            if ($line === null) {
                return [];
            }

            $response = $this->httpClient->request('GET', $this->apiBaseUrl . "/lines/{$line['id']}/stop_areas", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => ['count' => $limit],
                'timeout' => 5,
            ]);

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
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/places', [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'q' => $query,
                    'type[]' => 'stop_area',
                    'count' => $type ? max($limit * 3, 30) : $limit,
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            return $this->normalizePlacesResponse($data['places'] ?? [], $type, $limit);
        } catch (\Throwable) {
            return $this->getMockSearchResults($query, $type, $limit);
        }
    }

    // ─── Nearby Stops ──────────────────────────────────────────────────────

    public function getNearbyStops(float $lat, float $lon, int $radius = 500, ?string $type = null): array
    {
        $cacheKey = 'nearby_' . md5("{$lat}_{$lon}_{$radius}_{$type}");
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
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . "/coord/{$coord}/places_nearby", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'type[]' => 'stop_area',
                    'distance' => $radius,
                    'count' => 20,
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            return $this->normalizeNearbyResponse($data, $type);
        } catch (\Throwable) {
            return $this->getMockNearbyStops($lat, $lon, $radius, $type);
        }
    }

    // ─── Departures ────────────────────────────────────────────────────────

    public function getNextDepartures(string $stopId): array
    {
        $cacheKey = 'departures_' . md5($stopId);
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = empty($this->apiKey)
            ? $this->getMockDepartures($stopId)
            : $this->fetchDeparturesFromApi($stopId);

        $cacheItem->set($data)->expiresAfter(self::DEPARTURES_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    private function fetchDeparturesFromApi(string $stopId): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . "/stop_areas/{$stopId}/departures", [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'count' => 10,
                    'data_freshness' => 'realtime',
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            return $this->normalizeDeparturesResponse($data);
        } catch (\Throwable) {
            return $this->getMockDepartures($stopId);
        }
    }

    // ─── Traffic Alerts ────────────────────────────────────────────────────

    public function getTrafficAlerts(?string $lineId = null): array
    {
        $cacheKey = 'alerts_' . md5($lineId ?? 'all');
        $cacheItem = $this->cache->getItem($cacheKey);

        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        $data = empty($this->apiKey)
            ? $this->getMockAlerts()
            : $this->fetchAlertsFromApi($lineId);

        $cacheItem->set($data)->expiresAfter(self::ALERTS_TTL);
        $this->cache->save($cacheItem);

        return $data;
    }

    private function fetchAlertsFromApi(?string $lineId): array
    {
        try {
            $path = $lineId ? "/lines/{$lineId}/line_reports" : '/disruptions';
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . $path, [
                'headers' => ['apikey' => $this->apiKey],
                'query' => [
                    'count' => 50,
                    'since' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Ymd\THis'),
                ],
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            return $this->normalizeAlertsResponse($data);
        } catch (\Throwable) {
            return $this->getMockAlerts();
        }
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
            $dist = (int) ($place['distance'] ?? 0);
            $stop['distance'] = $dist;
            $stop['distanceLabel'] = $dist < 1000 ? "{$dist}m" : number_format($dist / 1000, 1) . 'km';
            $stops[] = $stop;
        }

        usort($stops, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice($stops, 0, 10);
    }

    private function normalizeStopArea(array $stopArea): array
    {
        $modeName = $this->pickPrimaryMode($stopArea['commercial_modes'] ?? []);

        $lines = [];
        foreach (array_slice($stopArea['lines'] ?? [], 0, 8) as $line) {
            $lineMode = $line['commercial_mode']['name'] ?? 'bus';
            $lines[] = $this->formatLineLabel($lineMode, $line['code'] ?? ($line['name'] ?? '?'));
        }

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
            if ($departureTime === null) {
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
        return array_slice($departures, 0, 5);
    }

    private function normalizeAlertsResponse(array $data): array
    {
        $alerts = [];
        foreach ($data['disruptions'] ?? [] as $disruption) {
            if (($disruption['status'] ?? 'active') === 'past') {
                continue;
            }
            $categoryText = strtolower(implode(' ', array_filter([
                $disruption['cause'] ?? '',
                $disruption['category'] ?? '',
                implode(' ', $disruption['tags'] ?? []),
            ])));

            [$lineCode, $transportType] = $this->extractImpactedLine($disruption['impacted_objects'] ?? []);

            $startDate = $this->parseNavitiaDate($disruption['application_periods'][0]['begin'] ?? null);
            $endDate = $this->parseNavitiaDate($disruption['application_periods'][0]['end'] ?? null);

            $alerts[] = [
                'id' => $disruption['id'] ?? uniqid(),
                'lineCode' => $lineCode,
                'transportType' => $transportType,
                'severity' => $this->mapSeverity($disruption['severity']['effect'] ?? ($disruption['severity']['name'] ?? 'INFO')),
                'category' => $this->mapCategory($categoryText),
                'title' => $this->extractMessage($disruption['messages'] ?? [], 'title') ?? 'Perturbation en cours',
                'description' => $this->extractMessage($disruption['messages'] ?? [], 'web') ?? '',
                'estimatedResume' => null,
                'startDate' => $startDate?->format('c') ?? date('c'),
                'endDate' => $endDate?->format('c'),
            ];
        }
        return $alerts;
    }

    /** @return array{0: string, 1: string} [lineCode, transportType] */
    private function extractImpactedLine(array $impactedObjects): array
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

        return [$impactedObjects[0]['pt_object']['name'] ?? 'Inconnue', 'METRO'];
    }

    private function extractMessage(array $messages, string $channelType): ?string
    {
        foreach ($messages as $message) {
            if (in_array($channelType, $message['channel']['types'] ?? [], true)) {
                return trim(strip_tags($message['text'] ?? '')) ?: null;
            }
        }

        // Fallback : premier message disponible (utile pour le titre)
        if ($channelType === 'title' && isset($messages[0]['text'])) {
            return trim(strip_tags($messages[0]['text'])) ?: null;
        }

        return null;
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
        $priority = ['METRO' => 1, 'RER' => 2, 'TRAM' => 3, 'BUS' => 4];
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
        return match($this->mapTransportType($modeName)) {
            'METRO' => 'M' . $code,
            'RER' => 'RER ' . $code,
            default => $code,
        };
    }

    private function mapTransportType(string $mode): string
    {
        return match(strtolower($mode)) {
            'metro', 'métro', 'subway' => 'METRO',
            'rer', 'rail', 'train', 'rapid_transit', 'rapidtransit', 'localtrain', 'local train' => 'RER',
            'tram', 'tramway', 'tram_train' => 'TRAM',
            default => 'BUS',
        };
    }

    private function mapSeverity(string $severity): string
    {
        return match(strtolower($severity)) {
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

    private function getMockSearchResults(string $query, ?string $type, int $limit): array
    {
        $allStops = $this->getAllMockStops();
        $query = strtolower($query);

        $filtered = array_filter($allStops, function ($stop) use ($query, $type) {
            $nameMatch = str_contains(strtolower($stop['name']), $query);
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
            $stops = array_filter($stops, fn($s) => $s['transportType'] === strtoupper($type));
        }

        // Add mock distances
        foreach ($stops as &$stop) {
            $dlat = abs($stop['lat'] - $lat) * 111000;
            $dlon = abs($stop['lon'] - $lon) * 111000 * cos(deg2rad($lat));
            $dist = (int) sqrt($dlat ** 2 + $dlon ** 2);
            $stop['distance'] = $dist;
            $stop['distanceLabel'] = $dist < 1000 ? "{$dist}m" : number_format($dist / 1000, 1) . 'km';
        }

        // Sort by distance
        usort($stops, fn($a, $b) => $a['distance'] <=> $b['distance']);

        return array_slice(array_values($stops), 0, 10);
    }

    private function getMockDepartures(string $stopId): array
    {
        $lines = $this->getMockLinesForStop($stopId);
        $departures = [];

        foreach ($lines as $line) {
            $base = random_int(1, 4);
            for ($i = 0; $i < 3; $i++) {
                $wait = $base + ($i * random_int(4, 7));
                $departures[] = [
                    'lineCode' => $line['code'],
                    'transportType' => $line['type'],
                    'direction' => $line['direction'],
                    'waitMinutes' => $wait,
                    'isRealtime' => true,
                    'platform' => null,
                ];
            }
        }

        return $departures;
    }

    private function getMockAlerts(): array
    {
        return [
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
    }

    private function getAllMockStops(): array
    {
        return [
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

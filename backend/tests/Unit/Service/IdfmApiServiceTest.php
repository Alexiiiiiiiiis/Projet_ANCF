<?php

namespace App\Tests\Unit\Service;

use App\Service\GeoService;
use App\Service\IdfmApiService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class IdfmApiServiceTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private CacheItemPoolInterface $cache;
    private CacheItemInterface $cacheItem;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cacheItem = $this->createMock(CacheItemInterface::class);

        $this->cacheItem->method('isHit')->willReturn(false);
        $this->cacheItem->method('set')->willReturnSelf();
        $this->cacheItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('getItem')->willReturn($this->cacheItem);
        $this->cache->method('save')->willReturn(true);
    }

    private function createService(string $apiKey = ''): IdfmApiService
    {
        return new IdfmApiService(
            $this->httpClient,
            $this->cache,
            $apiKey,
            'https://prim.iledefrance-mobilites.fr/marketplace/v2/navitia',
            'https://prim.iledefrance-mobilites.fr/marketplace',
            new GeoService(),
        );
    }

    // ─── Mock data tests (no API key) ─────────────────────────────────────

    public function testSearchStopsReturnsMockDataWithoutApiKey(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('Châtelet');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $this->assertSame('Châtelet', $results[0]['name']);
        $this->assertArrayHasKey('id', $results[0]);
        $this->assertArrayHasKey('lat', $results[0]);
        $this->assertArrayHasKey('lon', $results[0]);
        $this->assertArrayHasKey('transportType', $results[0]);
        $this->assertArrayHasKey('lines', $results[0]);
    }

    public function testSearchStopsFiltersByType(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('', 'RER');

        foreach ($results as $stop) {
            $this->assertSame('RER', $stop['transportType']);
        }
    }

    public function testSearchStopsRespectsLimit(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('', null, 3);

        $this->assertLessThanOrEqual(3, count($results));
    }

    public function testSearchStopsNoMatchReturnsEmpty(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('xyznonexistent');

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testGetNearbyStopsReturnsSortedByDistance(): void
    {
        $service = $this->createService('');
        $results = $service->getNearbyStops(48.8596, 2.3473, 5000);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // Check sorted by distance
        $distances = array_column($results, 'distance');
        for ($i = 1; $i < count($distances); ++$i) {
            $this->assertGreaterThanOrEqual($distances[$i - 1], $distances[$i]);
        }
    }

    public function testGetNearbyStopsHasDistanceLabels(): void
    {
        $service = $this->createService('');
        $results = $service->getNearbyStops(48.8596, 2.3473, 5000);

        foreach ($results as $stop) {
            $this->assertArrayHasKey('distance', $stop);
            $this->assertArrayHasKey('distanceLabel', $stop);
            $this->assertIsInt($stop['distance']);
            $this->assertMatchesRegularExpression('/^\d+m$|^\d+\.\d+km$/', $stop['distanceLabel']);
        }
    }

    public function testGetNearbyStopsRespectsRadius(): void
    {
        $service = $this->createService('');
        // Châtelet est un arrêt mock exactement à ces coordonnées : avec un rayon de 100m,
        // seul lui (distance 0) doit être retourné, pas des arrêts mock à des kilomètres.
        $results = $service->getNearbyStops(48.8596, 2.3473, 100);

        $this->assertNotEmpty($results);
        foreach ($results as $stop) {
            $this->assertLessThanOrEqual(100, $stop['distance']);
        }
    }

    public function testGetNearbyStopsFiltersByType(): void
    {
        $service = $this->createService('');
        $results = $service->getNearbyStops(48.8596, 2.3473, 50000, 'METRO');

        foreach ($results as $stop) {
            $this->assertSame('METRO', $stop['transportType']);
        }
    }

    public function testGetNextDeparturesReturnsMockData(): void
    {
        $service = $this->createService('');
        $results = $service->getNextDepartures('stop:M14:saint_lazare');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        $departure = $results[0];
        $this->assertArrayHasKey('lineCode', $departure);
        $this->assertArrayHasKey('transportType', $departure);
        $this->assertArrayHasKey('direction', $departure);
        $this->assertArrayHasKey('waitMinutes', $departure);
        $this->assertArrayHasKey('isRealtime', $departure);
        // Donnees fabriquees : elles ne doivent jamais se presenter comme du temps reel.
        $this->assertFalse($departure['isRealtime']);
    }

    public function testGetNextDeparturesUnknownStopReturnsFallback(): void
    {
        $service = $this->createService('');
        $results = $service->getNextDepartures('stop:unknown:xyz');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        // Falls back to default M1 line
        $this->assertSame('M1', $results[0]['lineCode']);
    }

    public function testGetTrafficAlertsReturnsMockAlerts(): void
    {
        $service = $this->createService('');
        $results = $service->getTrafficAlerts();

        $this->assertIsArray($results);
        $this->assertCount(4, $results);

        $alert = $results[0];
        $this->assertArrayHasKey('id', $alert);
        $this->assertArrayHasKey('lineCode', $alert);
        $this->assertArrayHasKey('transportType', $alert);
        $this->assertArrayHasKey('severity', $alert);
        $this->assertArrayHasKey('title', $alert);
        $this->assertArrayHasKey('description', $alert);
        $this->assertContains($alert['severity'], ['MAJOR', 'MODERATE', 'INFO']);
    }

    // ─── Line search tests (F2.2) ─────────────────────────────────────────

    public function testSearchByLineReturnsMockStopsOfLine(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('M14');

        $this->assertNotEmpty($results);
        foreach ($results as $stop) {
            $this->assertContains('M14', $stop['lines']);
        }
    }

    public function testSearchByLineRerQueryFiltersMockStops(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('RER B');

        $this->assertNotEmpty($results);
        foreach ($results as $stop) {
            $this->assertContains('RER B', $stop['lines']);
        }
    }

    public function testSearchByLineCallsPtObjectsThenLineStopAreas(): void
    {
        $ptObjectsResponse = $this->createMock(ResponseInterface::class);
        $ptObjectsResponse->method('toArray')->willReturn([
            'pt_objects' => [
                [
                    'embedded_type' => 'line',
                    'line' => [
                        'id' => 'line:IDFM:C01742',
                        'name' => 'RER A',
                        'code' => 'A',
                        'commercial_mode' => ['name' => 'RER'],
                    ],
                ],
            ],
        ]);

        $stopAreasResponse = $this->createMock(ResponseInterface::class);
        $stopAreasResponse->method('toArray')->willReturn([
            'stop_areas' => [
                ['id' => 'stop_area:IDFM:478926', 'name' => 'Auber', 'coord' => ['lat' => '48.872', 'lon' => '2.329']],
            ],
        ]);

        $this->httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls($ptObjectsResponse, $stopAreasResponse);

        $service = $this->createService('test-api-key');
        $results = $service->searchStops('RER A');

        $this->assertCount(1, $results);
        $this->assertSame('Auber', $results[0]['name']);
        $this->assertSame('RER', $results[0]['transportType']);
        $this->assertSame(['RER A'], $results[0]['lines']);
    }

    public function testNonLineQueryDoesNotTriggerLineSearch(): void
    {
        $service = $this->createService('');
        $results = $service->searchStops('Châtelet');

        // "Châtelet" ne matche pas le motif de ligne → recherche classique
        $this->assertSame('Châtelet', $results[0]['name']);
    }

    // ─── Cache tests ──────────────────────────────────────────────────────

    public function testSearchStopsReturnsCachedData(): void
    {
        $cachedData = [['id' => 'cached', 'name' => 'Cached Stop']];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedData);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', 'https://siri.test', new GeoService());

        $results = $service->searchStops('test');
        $this->assertSame($cachedData, $results);
    }

    public function testGetTrafficAlertsCacheHitReturnsDirectly(): void
    {
        $cachedAlerts = [['id' => 'alert-cached', 'severity' => 'INFO']];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedAlerts);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', 'https://siri.test', new GeoService());

        $results = $service->getTrafficAlerts();
        $this->assertSame($cachedAlerts, $results);
    }

    // ─── API call tests (with API key) ────────────────────────────────────

    public function testSearchStopsCallsApiWhenKeyProvided(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'places' => [
                [
                    'id' => 'stop_area:IDFM:123',
                    'name' => 'Gare du Nord (Paris)',
                    'embedded_type' => 'stop_area',
                    'stop_area' => [
                        'id' => 'stop_area:IDFM:123',
                        'name' => 'Gare du Nord',
                        'coord' => ['lat' => '48.8809', 'lon' => '2.3553'],
                        'commercial_modes' => [['id' => 'commercial_mode:Metro', 'name' => 'Métro']],
                        'lines' => [
                            ['code' => '4', 'name' => '4', 'commercial_mode' => ['name' => 'Métro']],
                            ['code' => '5', 'name' => '5', 'commercial_mode' => ['name' => 'Métro']],
                        ],
                    ],
                ],
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/places'))
            ->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->searchStops('gare');

        $this->assertCount(1, $results);
        $this->assertSame('Gare du Nord', $results[0]['name']);
        $this->assertSame('METRO', $results[0]['transportType']);
        $this->assertSame(['M4', 'M5'], $results[0]['lines']);
        $this->assertSame(48.8809, $results[0]['lat']);
    }

    public function testSearchStopsFallsBackToMockOnApiError(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('API timeout'));

        $service = $this->createService('test-api-key');
        $results = $service->searchStops('Châtelet');

        // Should fall back to mock data
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testGetDeparturesFallsBackToNavitiaWhenSiriIsEmpty(): void
    {
        $inFiveMinutes = (new \DateTimeImmutable('+5 minutes', new \DateTimeZone('Europe/Paris')))
            ->format('Ymd\THis');

        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'departures' => [
                [
                    'display_informations' => [
                        'code' => '1',
                        'direction' => 'La Défense (Puteaux)',
                        'commercial_mode' => 'Métro',
                    ],
                    'stop_date_time' => [
                        'departure_date_time' => $inFiveMinutes,
                        'data_freshness' => 'realtime',
                    ],
                    'stop_point' => ['platform_code' => 'Quai 1'],
                ],
            ],
        ]);

        $siriResponse = $this->createMock(ResponseInterface::class);
        $siriResponse->method('toArray')->willReturn([]); // SIRI muet : aucun passage suivi

        $this->httpClient->method('request')->willReturnCallback(
            fn (string $method, string $url) => str_contains($url, 'stop-monitoring') ? $siriResponse : $apiResponse
        );

        $service = $this->createService('test-api-key');
        $results = $service->getNextDepartures('stop_area:IDFM:71264');

        $this->assertCount(1, $results);
        $this->assertSame('M1', $results[0]['lineCode']);
        $this->assertSame('METRO', $results[0]['transportType']);
        $this->assertSame('La Défense', $results[0]['direction']);
        $this->assertSame('Quai 1', $results[0]['platform']);
        $this->assertTrue($results[0]['isRealtime']);
        $this->assertGreaterThanOrEqual(4, $results[0]['waitMinutes']);
        $this->assertLessThanOrEqual(5, $results[0]['waitMinutes']);
    }

    /**
     * @param array<int, array<string, mixed>> $visites
     */
    private function reponsesSiri(array $visites): callable
    {
        $siri = $this->createMock(ResponseInterface::class);
        $siri->method('toArray')->willReturn([
            'Siri' => ['ServiceDelivery' => ['StopMonitoringDelivery' => [['MonitoredStopVisit' => $visites]]]],
        ]);

        // Navitia ne sert plus qu'a traduire STIF:Line::C01107: en "72".
        $lignes = $this->createMock(ResponseInterface::class);
        $lignes->method('toArray')->willReturn([
            'lines' => [
                ['id' => 'line:IDFM:C01107', 'code' => '72', 'commercial_mode' => ['name' => 'Bus']],
                ['id' => 'line:IDFM:C01374', 'code' => '4', 'commercial_mode' => ['name' => 'Métro']],
                ['id' => 'line:IDFM:C01742', 'code' => 'A', 'commercial_mode' => ['name' => 'RER']],
            ],
        ]);

        return fn (string $method, string $url) => str_contains($url, 'stop-monitoring') ? $siri : $lignes;
    }

    /**
     * @return array<string, mixed>
     */
    private function visiteSiri(string $ligne, string $destination, string $heure, bool $tempsReel = true): array
    {
        $call = ['DestinationDisplay' => [['value' => $destination]]];
        $call[$tempsReel ? 'ExpectedDepartureTime' : 'AimedDepartureTime'] = $heure;

        return ['MonitoredVehicleJourney' => [
            'LineRef' => ['value' => 'STIF:Line::'.$ligne.':'],
            'MonitoredCall' => $call,
        ]];
    }

    public function testSearchStopsKeepsStructuringLinesOverBuses(): void
    {
        // Navitia renvoie les 27 lignes de La Defense en commencant par le metro puis les bus :
        // tronquer sans trier faisait disparaitre le RER A et le RER E des badges de l arret,
        // alors qu ils apparaissent dans ses departs.
        $lignes = [['commercial_mode' => ['name' => 'Métro'], 'code' => '1']];
        foreach (['73', '141', '144', '159', '174', '178', '258'] as $bus) {
            $lignes[] = ['commercial_mode' => ['name' => 'Bus'], 'code' => $bus];
        }
        $lignes[] = ['commercial_mode' => ['name' => 'RER'], 'code' => 'A'];
        $lignes[] = ['commercial_mode' => ['name' => 'RER'], 'code' => 'E'];
        $lignes[] = ['commercial_mode' => ['name' => 'Tramway'], 'code' => 'T2'];

        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'places' => [[
                'embedded_type' => 'stop_area',
                'stop_area' => [
                    'id' => 'stop_area:IDFM:71517',
                    'name' => 'La Défense',
                    'coord' => ['lat' => 48.892, 'lon' => 2.238],
                    'commercial_modes' => [['name' => 'Métro'], ['name' => 'RER']],
                    'lines' => $lignes,
                ],
            ]],
        ]);

        $this->httpClient->method('request')->willReturn($apiResponse);

        $results = $this->createService('test-api-key')->searchStops('La Défense');

        $this->assertCount(1, $results);
        $this->assertContains('RER A', $results[0]['lines']);
        $this->assertContains('RER E', $results[0]['lines']);
        $this->assertContains('T2', $results[0]['lines']);
        // Le mode le plus structurant ouvre la liste, les bus ferment la marche.
        $this->assertSame('M1', $results[0]['lines'][0]);
        $this->assertLessThanOrEqual(8, count($results[0]['lines']));
    }

    public function testGetDeparturesUsesSiriRealtimeTimes(): void
    {
        $dansSixMinutes = (new \DateTimeImmutable('+6 minutes'))->format(DATE_ATOM);

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri([
            $this->visiteSiri('C01107', 'Parc de Saint-Cloud (Saint-Cloud)', $dansSixMinutes),
        ]));

        $results = $this->createService('test-api-key')->getNextDepartures('stop_area:IDFM:71249');

        $this->assertCount(1, $results);
        $this->assertSame('72', $results[0]['lineCode']);
        $this->assertSame('BUS', $results[0]['transportType']);
        $this->assertSame('Parc de Saint-Cloud', $results[0]['direction']);
        $this->assertTrue($results[0]['isRealtime']);
        $this->assertGreaterThanOrEqual(5, $results[0]['waitMinutes']);
        $this->assertLessThanOrEqual(6, $results[0]['waitMinutes']);
    }

    public function testGetDeparturesFiltersByTransportTypeBeforeTruncating(): void
    {
        // Six bus partent avant les deux RER : si la coupe a cinq passait avant le filtre,
        // demander le RER ne renverrait rien alors que des trains partent bien de cet arret.
        $visites = [];
        foreach ([1, 2, 3, 4, 5, 6] as $minutes) {
            $visites[] = $this->visiteSiri('C01107', 'Rhin Et Danube', (new \DateTimeImmutable("+{$minutes} minutes"))->format(DATE_ATOM));
        }
        $visites[] = $this->visiteSiri('C01742', 'Boissy-Saint-Léger', (new \DateTimeImmutable('+8 minutes'))->format(DATE_ATOM));
        $visites[] = $this->visiteSiri('C01742', 'Saint-Germain-en-Laye', (new \DateTimeImmutable('+9 minutes'))->format(DATE_ATOM));

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri($visites));
        $service = $this->createService('test-api-key');

        $tous = $service->getNextDepartures('stop_area:IDFM:71517');
        $this->assertCount(5, $tous);
        $this->assertSame(['BUS'], array_unique(array_column($tous, 'transportType')));

        $rer = $service->getNextDepartures('stop_area:IDFM:71517', 'RER');
        $this->assertCount(2, $rer);
        $this->assertSame(['RER'], array_unique(array_column($rer, 'transportType')));
        $this->assertSame('Boissy-Saint-Léger', $rer[0]['direction']);
    }

    // --- Lignes (parcours Horaires) -----------------------------------------

    public function testGetLinesSortsCodesNaturally(): void
    {
        // Sans cle API, les lignes structurantes servent de catalogue : « 10 » doit suivre
        // « 9 », la ou un tri alphabetique le placerait entre « 1 » et « 2 ».
        $codes = array_column($this->createService('')->getLines('METRO'), 'code');

        $this->assertSame(
            ['1', '2', '3', '3B', '4', '5', '6', '7', '7B', '8', '9', '10', '11', '12', '13', '14'],
            $codes
        );
    }

    public function testGetLinesRejectsUnknownMode(): void
    {
        $this->assertSame([], $this->createService('')->getLines('TROTTINETTE'));
    }

    public function testGetLinesReadsCodeColorAndNetworkFromApi(): void
    {
        // L'onglet RER interroge deux modes commerciaux : le RER et le Transilien.
        $rer = $this->createMock(ResponseInterface::class);
        $rer->method('toArray')->willReturn(['lines' => [
            ['id' => 'line:IDFM:C01742', 'code' => 'A', 'color' => 'EB2132', 'text_color' => 'FFFFFF',
                'commercial_mode' => ['name' => 'RER'], 'network' => ['name' => 'RATP']],
        ]]);

        $transilien = $this->createMock(ResponseInterface::class);
        $transilien->method('toArray')->willReturn(['lines' => [
            ['id' => 'line:IDFM:C01744', 'code' => 'H', 'color' => '84653D',
                'commercial_mode' => ['name' => 'Train Transilien'], 'network' => ['name' => 'SNCF']],
        ]]);

        $this->httpClient->method('request')->willReturnCallback(
            fn (string $method, string $url) => str_contains($url, 'RapidTransit') ? $rer : $transilien
        );

        $lignes = $this->createService('test-api-key')->getLines('RER');

        $this->assertSame(['A', 'H'], array_column($lignes, 'code'));
        // Le libelle suit l app IDFM (« RER A », « Train H »)  la ou lineCode reste le code
        // annonce par les horaires, celui qui filtre les departs d un arret.
        $this->assertSame(['RER A', 'Train H'], array_column($lignes, 'label'));
        $this->assertSame(['RER A', 'H'], array_column($lignes, 'lineCode'));
        $this->assertSame(['#EB2132', '#84653D'], array_column($lignes, 'color'));
        $this->assertSame(['RATP', 'SNCF'], array_column($lignes, 'network'));
    }

    public function testGetLineStopsSortsByNameAndKeepsTheLineMode(): void
    {
        $ligne = $this->createMock(ResponseInterface::class);
        $ligne->method('toArray')->willReturn(['lines' => [
            ['id' => 'line:IDFM:C01742', 'code' => 'A', 'commercial_mode' => ['name' => 'RER']],
        ]]);

        $arrets = $this->createMock(ResponseInterface::class);
        $arrets->method('toArray')->willReturn(['stop_areas' => [
            ['id' => 'stop_area:IDFM:1', 'name' => 'Auber', 'coord' => ['lat' => '48.87', 'lon' => '2.32']],
            ['id' => 'stop_area:IDFM:2', 'name' => 'Acheres Ville', 'coord' => ['lat' => '48.97', 'lon' => '2.07']],
        ]]);

        $this->httpClient->method('request')->willReturnCallback(
            fn (string $method, string $url) => str_contains($url, 'stop_areas') ? $arrets : $ligne
        );

        $stops = $this->createService('test-api-key')->getLineStops('line:IDFM:C01742');

        $this->assertSame(['Acheres Ville', 'Auber'], array_column($stops, 'name'));
        // Navitia n annonce pas les modes des arrets d une ligne : sans reprise du mode de la
        // ligne, tous les arrets du RER A s afficheraient en bus.
        $this->assertSame(['RER', 'RER'], array_column($stops, 'transportType'));
        $this->assertSame([['RER A'], ['RER A']], array_column($stops, 'lines'));
    }

    public function testGetDeparturesFiltersByLine(): void
    {
        // A Gare du Nord on veut les passages du RER A sans ceux du metro : le filtre porte sur
        // le code public de la ligne, quels que soient sa casse et ses espaces.
        $visites = [];
        foreach ([['C01374', 2], ['C01742', 4], ['C01374', 6], ['C01742', 8]] as [$ligne, $minutes]) {
            $visites[] = $this->visiteSiri($ligne, 'Terminus', (new \DateTimeImmutable("+{$minutes} minutes"))->format(DATE_ATOM));
        }

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri($visites));
        $service = $this->createService('test-api-key');

        $rer = $service->getNextDepartures('stop_area:IDFM:71410', null, 'rer a');

        $this->assertCount(2, $rer);
        $this->assertSame(['RER A'], array_unique(array_column($rer, 'lineCode')));
    }

    public function testGetDeparturesLimitRaisesTheNumberOfResults(): void
    {
        // La page Horaires affiche tous les passages d'un arret, la ou l'accueil s'en tient aux
        // cinq prochains : c'est le seul role de ?limit.
        $visites = [];
        foreach (range(1, 8) as $minutes) {
            $visites[] = $this->visiteSiri('C01107', 'Rhin Et Danube', (new \DateTimeImmutable("+{$minutes} minutes"))->format(DATE_ATOM));
        }

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri($visites));
        $service = $this->createService('test-api-key');

        $this->assertCount(5, $service->getNextDepartures('stop_area:IDFM:71249'));
        $this->assertCount(8, $service->getNextDepartures('stop_area:IDFM:71249', null, null, 40));
    }

    public function testGetDepartureLinesListsEachLineOnceOrderedByMode(): void
    {
        $visites = [
            $this->visiteSiri('C01107', 'Rhin Et Danube', (new \DateTimeImmutable('+2 minutes'))->format(DATE_ATOM)),
            $this->visiteSiri('C01742', 'Boissy-Saint-Leger', (new \DateTimeImmutable('+3 minutes'))->format(DATE_ATOM)),
            $this->visiteSiri('C01107', 'Rhin Et Danube', (new \DateTimeImmutable('+9 minutes'))->format(DATE_ATOM)),
            $this->visiteSiri('C01374', 'Porte de Clignancourt', (new \DateTimeImmutable('+4 minutes'))->format(DATE_ATOM)),
        ];

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri($visites));

        $lignes = $this->createService('test-api-key')->getDepartureLines('stop_area:IDFM:71410');

        // Le bus 72 passe deux fois mais ne doit apparaitre qu'une, et apres les modes lourds.
        $this->assertSame(['M4', 'RER A', '72'], array_column($lignes, 'lineCode'));
        $this->assertSame(['METRO', 'RER', 'BUS'], array_column($lignes, 'transportType'));
    }

    public function testGetDeparturesWithoutMatchingTypeReturnsNothing(): void
    {
        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri([
            $this->visiteSiri('C01107', 'Rhin Et Danube', (new \DateTimeImmutable('+3 minutes'))->format(DATE_ATOM)),
        ]));

        // Aucun tram a cet arret : la liste doit etre vide, pas retomber sur les autres modes.
        $this->assertSame([], $this->createService('test-api-key')->getNextDepartures('stop_area:IDFM:71249', 'TRAM'));
    }

    public function testGetDeparturesMarksAimedTimesAsNotRealtime(): void
    {
        $dansDixMinutes = (new \DateTimeImmutable('+10 minutes'))->format(DATE_ATOM);

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri([
            $this->visiteSiri('C01374', 'Porte de Clignancourt', $dansDixMinutes, false),
        ]));

        $results = $this->createService('test-api-key')->getNextDepartures('stop_area:IDFM:71264');

        $this->assertSame('M4', $results[0]['lineCode']);
        $this->assertSame('METRO', $results[0]['transportType']);
        // Horaire theorique faute de vehicule suivi : l'app ne doit pas parler de temps reel.
        $this->assertFalse($results[0]['isRealtime']);
    }

    public function testGetDeparturesSortsSiriVisitsByTimeAndDropsPastOnes(): void
    {
        $passe = (new \DateTimeImmutable('-5 minutes'))->format(DATE_ATOM);
        $tot = (new \DateTimeImmutable('+2 minutes'))->format(DATE_ATOM);
        $tard = (new \DateTimeImmutable('+12 minutes'))->format(DATE_ATOM);

        // SIRI livre ses visites groupees par ligne : le tri chronologique est a notre charge.
        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri([
            $this->visiteSiri('C01374', 'Bagneux', $tard),
            $this->visiteSiri('C01107', 'Hôtel de Ville', $passe),
            $this->visiteSiri('C01107', 'Parc de Saint-Cloud', $tot),
        ]));

        $results = $this->createService('test-api-key')->getNextDepartures('stop_area:IDFM:71264');

        $this->assertCount(2, $results, 'le passage deja effectue doit disparaitre');
        $this->assertSame('72', $results[0]['lineCode']);
        $this->assertSame('M4', $results[1]['lineCode']);
    }

    public function testGetDeparturesIgnoresUnidentifiableLines(): void
    {
        $dansCinqMinutes = (new \DateTimeImmutable('+5 minutes'))->format(DATE_ATOM);

        $this->httpClient->method('request')->willReturnCallback($this->reponsesSiri([
            $this->visiteSiri('C09999', 'Terminus inconnu', $dansCinqMinutes),
        ]));

        $results = $this->createService('test-api-key')->getNextDepartures('stop_area:IDFM:71264');

        // Ligne absente du referentiel : mieux vaut ne rien afficher qu'un code invente.
        $this->assertSame([], $results);
    }

    public function testGetAlertsCallsApiWhenKeyProvided(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'disruptions' => [
                [
                    'id' => 'disruption-1',
                    'cause' => 'perturbation',
                    'category' => 'Incidents',
                    'impacted_objects' => [
                        [
                            'pt_object' => [
                                'embedded_type' => 'line',
                                'name' => '4',
                                'line' => [
                                    'code' => '4',
                                    'commercial_mode' => ['name' => 'Métro'],
                                ],
                            ],
                        ],
                    ],
                    'status' => 'active',
                    'severity' => ['name' => 'bloquante', 'effect' => 'NO_SERVICE'],
                    'messages' => [
                        ['text' => 'Trafic interrompu', 'channel' => ['types' => ['title']]],
                        ['text' => '<p>Incident technique en gare</p>', 'channel' => ['types' => ['web']]],
                    ],
                    'application_periods' => [['begin' => '20260329T100000']],
                ],
                [
                    'id' => 'disruption-2',
                    'status' => 'past',
                    'severity' => ['effect' => 'NO_SERVICE'],
                    'messages' => [['text' => 'Perturbation terminée', 'channel' => ['types' => ['title']]]],
                ],
            ],
        ]);

        // getTrafficAlerts() (sans lineId) interroge chaque ligne structurante (RER/Métro/Tram/Transilien)
        // en plus du flux global /disruptions, pour ne rater aucune perturbation majeure. Le même
        // jeu de données est donc renvoyé pour chaque appel ; la déduplication par id doit ramener
        // le résultat à une seule alerte (disruption-2 est filtrée car "past").
        $this->httpClient->method('request')->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->getTrafficAlerts();

        $this->assertCount(1, $results);
        $this->assertSame('disruption-1', $results[0]['id']);
        $this->assertSame('MAJOR', $results[0]['severity']);
        $this->assertSame('M4', $results[0]['lineCode']);
        $this->assertSame('METRO', $results[0]['transportType']);
        $this->assertSame('INCIDENT', $results[0]['category']);
        $this->assertSame('Trafic interrompu', $results[0]['title']);
        $this->assertSame('Incident technique en gare', $results[0]['description']);
    }

    public function testMergeRecurringAlertsFillsInMissingEndDate(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'disruptions' => [
                [
                    'id' => 'occurrence-1',
                    'status' => 'active',
                    'severity' => ['effect' => 'SIGNIFICANT_DELAYS'],
                    'messages' => [
                        ['text' => 'Grands travaux', 'channel' => ['types' => ['title']]],
                        ['text' => 'Fermeture prolongée', 'channel' => ['types' => ['web']]],
                    ],
                    // Cette occurrence précise n'a pas de date de fin renseignée
                    'application_periods' => [['begin' => '20260805T030000']],
                ],
                [
                    'id' => 'occurrence-2',
                    'status' => 'active',
                    'severity' => ['effect' => 'SIGNIFICANT_DELAYS'],
                    'messages' => [
                        ['text' => 'Grands travaux', 'channel' => ['types' => ['title']]],
                        ['text' => 'Fermeture prolongée', 'channel' => ['types' => ['web']]],
                    ],
                    'application_periods' => [['begin' => '20260806T030000', 'end' => '20260807T030000']],
                ],
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/lines/line:IDFM:C01728/line_reports'))
            ->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->getTrafficAlerts('line:IDFM:C01728');

        $this->assertCount(1, $results, 'Les deux occurrences identiques (même ligne/titre/description) doivent être fusionnées');
        $this->assertNotNull(
            $results[0]['endDate'],
            'La date de fin connue sur une occurrence doit être conservée après fusion, même si la première occurrence rencontrée ne la fournissait pas'
        );
    }

    public function testJourneySectionsDistinguishWaitingFromWalking(): void
    {
        $depart = (new \DateTimeImmutable('+10 minutes'))->format('Ymd\THis');
        $milieu = (new \DateTimeImmutable('+30 minutes'))->format('Ymd\THis');
        $arrivee = (new \DateTimeImmutable('+50 minutes'))->format('Ymd\THis');

        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'journeys' => [[
                'departure_date_time' => $depart,
                'arrival_date_time' => $arrivee,
                'duration' => 2400,
                'nb_transfers' => 1,
                'sections' => [
                    [
                        'type' => 'public_transport',
                        'duration' => 1200,
                        'departure_date_time' => $depart,
                        'arrival_date_time' => $milieu,
                        'display_informations' => ['commercial_mode' => 'TER', 'code' => 'TER'],
                        'from' => ['name' => 'Versailles Chantiers'],
                        'to' => ['name' => 'Gare Montparnasse'],
                    ],
                    [
                        'type' => 'waiting',
                        'duration' => 300,
                        'departure_date_time' => $milieu,
                        'arrival_date_time' => $milieu,
                    ],
                    [
                        'type' => 'public_transport',
                        'duration' => 900,
                        'departure_date_time' => $milieu,
                        'arrival_date_time' => $arrivee,
                        'display_informations' => ['commercial_mode' => 'Métro', 'code' => '13'],
                        'from' => ['name' => 'Montparnasse Bienvenue'],
                        'to' => ['name' => 'Saint-Lazare'],
                    ],
                ],
            ]],
        ]);

        $this->httpClient->method('request')->willReturn($apiResponse);

        $journeys = $this->createService('test-api-key')->searchJourneys('stop_area:IDFM:63880', 'stop_area:IDFM:71370');
        $sections = $journeys[0]['sections'];

        // Un TER est un train : l affichage doit lui donner la couleur du RER, pas celle du bus.
        $this->assertSame('RER', $sections[0]['mode']);
        $this->assertSame('TER', $sections[0]['lineCode']);
        // Une attente sur le quai n est pas une marche.
        $this->assertSame('WAIT', $sections[1]['mode']);
        $this->assertSame('METRO', $sections[2]['mode']);
        $this->assertSame('M13', $sections[2]['lineCode']);
    }

    public function testGetAlertsIncludesStructuringLineReports(): void
    {
        // Les perturbations du reseau structurant sont collectees ligne par ligne, dans une
        // boucle qui avale ses exceptions : une erreur la vidait sans que rien ne le signale.
        $recemment = (new \DateTimeImmutable('-1 hour'))->format('Ymd\THis');

        $lineReports = $this->createMock(ResponseInterface::class);
        $lineReports->method('toArray')->willReturn([
            'disruptions' => [
                [
                    'id' => 'perturbation-structurante',
                    'status' => 'active',
                    'severity' => ['effect' => 'BLOCKING'],
                    'messages' => [['text' => 'Trafic interrompu', 'channel' => ['types' => ['title']]]],
                    'application_periods' => [['begin' => $recemment]],
                ],
            ],
        ]);

        $flux = $this->createMock(ResponseInterface::class);
        $flux->method('toArray')->willReturn(['disruptions' => []]);

        $this->httpClient->method('request')->willReturnCallback(
            fn (string $method, string $url) => str_contains($url, 'line_reports') ? $lineReports : $flux
        );

        $results = $this->createService('test-api-key')->getTrafficAlerts();

        $ids = array_column($results, 'id');
        $this->assertContains('perturbation-structurante', $ids);
    }

    public function testGetLineAlertsCallsOnlyThatLine(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'disruptions' => [
                [
                    'id' => 'disruption-line',
                    'status' => 'active',
                    'severity' => ['effect' => 'BLOCKING'],
                    'messages' => [['text' => 'Trafic bloqué', 'channel' => ['types' => ['title']]]],
                    'application_periods' => [['begin' => '20260329T100000']],
                ],
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/lines/line:IDFM:C01728/line_reports'))
            ->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->getTrafficAlerts('line:IDFM:C01728');

        $this->assertCount(1, $results);
        $this->assertSame('disruption-line', $results[0]['id']);
    }

    public function testGetAlertsFallsBackToMockOnApiError(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('Network error'));

        $service = $this->createService('test-api-key');
        $results = $service->getTrafficAlerts();

        $this->assertIsArray($results);
        $this->assertCount(4, $results);
    }

    public function testGetNearbyStopsCallsApiWhenKeyProvided(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'places_nearby' => [
                [
                    'id' => 'stop_area:IDFM:456',
                    'embedded_type' => 'stop_area',
                    'distance' => '150',
                    'stop_area' => [
                        'id' => 'stop_area:IDFM:456',
                        'name' => 'Arrêt Proche',
                        'coord' => ['lat' => '48.860', 'lon' => '2.348'],
                        'commercial_modes' => [['id' => 'commercial_mode:Bus', 'name' => 'Bus']],
                        'lines' => [['code' => '72', 'name' => '72', 'commercial_mode' => ['name' => 'Bus']]],
                    ],
                ],
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/places_nearby'))
            ->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->getNearbyStops(48.8596, 2.3473, 500);

        $this->assertCount(1, $results);
        $this->assertSame('Arrêt Proche', $results[0]['name']);
        $this->assertSame('BUS', $results[0]['transportType']);
        // F6.4 : la distance à vol d'oiseau (150m) renvoyée par IDFM est corrigée du facteur
        // de détour piéton (×1.3) avant d'être exposée, pour approximer une distance de marche.
        $this->assertSame(195, $results[0]['distance']);
        $this->assertSame('195m', $results[0]['distanceLabel']);
    }

    // ─── Journeys (calcul d'itinéraire) ────────────────────────────────────

    public function testSearchJourneysReturnsMockDataWithoutApiKey(): void
    {
        $service = $this->createService('');
        $results = $service->searchJourneys('stop_area:IDFM:71264', 'stop_area:IDFM:71517');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        $journey = $results[0];
        $this->assertArrayHasKey('durationMinutes', $journey);
        $this->assertArrayHasKey('transfers', $journey);
        $this->assertArrayHasKey('sections', $journey);
        $this->assertNotEmpty($journey['sections']);
    }

    public function testSearchJourneysCacheHitReturnsDirectly(): void
    {
        $cachedJourneys = [['durationMinutes' => 12, 'transfers' => 0, 'sections' => []]];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($cachedJourneys);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', 'https://siri.test', new GeoService());

        $results = $service->searchJourneys('A', 'B');
        $this->assertSame($cachedJourneys, $results);
    }

    public function testSearchJourneysCallsApiWhenKeyProvided(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('toArray')->willReturn([
            'journeys' => [
                [
                    'duration' => 1620,
                    'nb_transfers' => 1,
                    'departure_date_time' => '20260728T090000',
                    'arrival_date_time' => '20260728T092700',
                    'sections' => [
                        [
                            'type' => 'public_transport',
                            'duration' => 840,
                            'departure_date_time' => '20260728T090000',
                            'arrival_date_time' => '20260728T091400',
                            'from' => ['name' => 'Châtelet'],
                            'to' => ['name' => 'Gare de Lyon'],
                            'display_informations' => [
                                'commercial_mode' => 'Métro',
                                'code' => '1',
                                'direction' => 'Château de Vincennes',
                            ],
                        ],
                        [
                            'type' => 'street_network',
                            'duration' => 30,
                        ],
                    ],
                ],
                [
                    'status' => 'NO_SOLUTION',
                    'sections' => [],
                ],
            ],
        ]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/journeys'))
            ->willReturn($apiResponse);

        $service = $this->createService('test-api-key');
        $results = $service->searchJourneys('stop_area:IDFM:71264', 'stop_area:IDFM:73626');

        $this->assertCount(1, $results);
        $this->assertSame(27, $results[0]['durationMinutes']);
        $this->assertSame(1, $results[0]['transfers']);
        $this->assertCount(1, $results[0]['sections']);
        $this->assertSame('METRO', $results[0]['sections'][0]['mode']);
        $this->assertSame('M1', $results[0]['sections'][0]['lineCode']);
        $this->assertSame('Château de Vincennes', $results[0]['sections'][0]['direction']);
        $this->assertSame(14, $results[0]['sections'][0]['durationMinutes']);
    }

    public function testSearchJourneysFallsBackToMockOnApiError(): void
    {
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('API timeout'));

        $service = $this->createService('test-api-key');
        $results = $service->searchJourneys('A', 'B');

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    // ─── Suivi du quota API (F7.3) ─────────────────────────────────────────

    public function testGetApiQuotaStatusReturnsNullWhenNeverRecorded(): void
    {
        $service = new IdfmApiService($this->httpClient, new ArrayAdapter(), '', 'https://api.test', 'https://siri.test', new GeoService());
        $this->assertNull($service->getApiQuotaStatus());
    }

    public function testApiQuotaStatusReportsTheMostConsumedApi(): void
    {
        // PRIM decompte SIRI et Navitia separement : c'est le compteur le plus bas qui dira
        // quand l'app basculera en mode degrade, c'est donc celui-la qu'il faut afficher.
        $siri = $this->createMock(ResponseInterface::class);
        $siri->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-day' => ['988'],
            'x-ratelimit-limit-day' => ['1000'],
        ]);
        $siri->method('toArray')->willReturn([]);

        $navitia = $this->createMock(ResponseInterface::class);
        $navitia->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-day' => ['312'],
            'x-ratelimit-limit-day' => ['1000'],
        ]);
        $navitia->method('toArray')->willReturn(['places' => []]);

        $this->httpClient->method('request')->willReturnCallback(
            fn (string $method, string $url) => str_contains($url, 'stop-monitoring') ? $siri : $navitia
        );

        $service = new IdfmApiService($this->httpClient, new ArrayAdapter(), 'test-api-key', 'https://api.test', 'https://siri.test', new GeoService());
        $service->getNextDepartures('stop_area:IDFM:71264');
        $service->searchStops('Châtelet');

        $status = $service->getApiQuotaStatus();
        $this->assertNotNull($status);
        $this->assertSame(312, $status['remaining']);
        $this->assertSame('navitia', $status['source']);
    }

    public function testRecordApiQuotaCapturesRateLimitHeaders(): void
    {
        $apiResponse = $this->createMock(ResponseInterface::class);
        $apiResponse->method('getHeaders')->willReturn([
            'x-ratelimit-remaining-day' => ['842'],
            'x-ratelimit-limit-day' => ['1000'],
        ]);
        $apiResponse->method('toArray')->willReturn(['places' => []]);

        $this->httpClient->method('request')->willReturn($apiResponse);

        $service = new IdfmApiService($this->httpClient, new ArrayAdapter(), 'test-api-key', 'https://api.test', 'https://siri.test', new GeoService());
        $service->searchStops('Châtelet');

        $status = $service->getApiQuotaStatus();
        $this->assertNotNull($status);
        $this->assertSame(842, $status['remaining']);
        $this->assertSame(1000, $status['limit']);
        $this->assertArrayHasKey('checkedAt', $status);
    }
}

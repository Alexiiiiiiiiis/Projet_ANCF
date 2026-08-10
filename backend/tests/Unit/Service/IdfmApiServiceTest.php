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
        $this->assertTrue($departure['isRealtime']);
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

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', new GeoService());

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

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', new GeoService());

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

    public function testGetDeparturesCallsApiWhenKeyProvided(): void
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

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/departures'))
            ->willReturn($apiResponse);

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

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test', new GeoService());

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
        $service = new IdfmApiService($this->httpClient, new ArrayAdapter(), '', 'https://api.test', new GeoService());
        $this->assertNull($service->getApiQuotaStatus());
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

        $service = new IdfmApiService($this->httpClient, new ArrayAdapter(), 'test-api-key', 'https://api.test', new GeoService());
        $service->searchStops('Châtelet');

        $status = $service->getApiQuotaStatus();
        $this->assertNotNull($status);
        $this->assertSame(842, $status['remaining']);
        $this->assertSame(1000, $status['limit']);
        $this->assertArrayHasKey('checkedAt', $status);
    }
}

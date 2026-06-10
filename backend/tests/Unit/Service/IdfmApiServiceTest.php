<?php

namespace App\Tests\Unit\Service;

use App\Service\IdfmApiService;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
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
            'https://prim.iledefrance-mobilites.fr/marketplace'
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
        for ($i = 1; $i < count($distances); $i++) {
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

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test');

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

        $service = new IdfmApiService($this->httpClient, $cache, '', 'https://api.test');

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

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/disruptions'))
            ->willReturn($apiResponse);

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
        $this->assertSame(150, $results[0]['distance']);
        $this->assertSame('150m', $results[0]['distanceLabel']);
    }
}

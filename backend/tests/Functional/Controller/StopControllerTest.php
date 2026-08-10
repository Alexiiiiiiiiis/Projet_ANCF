<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StopControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSearchReturnsStops(): void
    {
        $this->client->request('GET', '/api/stops/search?q=Chatelet');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('stops', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('query', $data);
        $this->assertSame('Chatelet', $data['query']);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testSearchWithTypeFilter(): void
    {
        $this->client->request('GET', '/api/stops/search?q=Gare&type=RER');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stops', $data);
    }

    public function testSearchTooShortReturns400(): void
    {
        $this->client->request('GET', '/api/stops/search?q=A');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchEmptyQueryReturns400(): void
    {
        $this->client->request('GET', '/api/stops/search?q=');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testNearbyReturnsStops(): void
    {
        $this->client->request('GET', '/api/stops/nearby?lat=48.8596&lon=2.3473');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stops', $data);
        $this->assertArrayHasKey('lat', $data);
        $this->assertArrayHasKey('lon', $data);
        $this->assertArrayHasKey('radius', $data);
    }

    public function testNearbyWithCustomRadius(): void
    {
        $this->client->request('GET', '/api/stops/nearby?lat=48.8596&lon=2.3473&radius=1000');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1000, $data['radius']);
    }

    public function testStopDetailReturnsDepartures(): void
    {
        $this->client->request('GET', '/api/stops/stop:M14:chatelet');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stopId', $data);
        $this->assertArrayHasKey('departures', $data);
        $this->assertSame('stop:M14:chatelet', $data['stopId']);
    }
}

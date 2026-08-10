<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ScheduleControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDeparturesReturnsScheduleData(): void
    {
        $this->client->request('GET', '/api/schedules/stop:M14:chatelet');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stopId', $data);
        $this->assertArrayHasKey('fetchedAt', $data);
        $this->assertArrayHasKey('refreshInterval', $data);
        $this->assertArrayHasKey('departures', $data);
        $this->assertSame('stop:M14:chatelet', $data['stopId']);
        $this->assertSame(30, $data['refreshInterval']);
    }

    public function testDeparturesContainsDepartureFields(): void
    {
        $this->client->request('GET', '/api/schedules/stop:RERA:gare_nord');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['departures']);

        $dep = $data['departures'][0];
        $this->assertArrayHasKey('lineCode', $dep);
        $this->assertArrayHasKey('transportType', $dep);
        $this->assertArrayHasKey('direction', $dep);
        $this->assertArrayHasKey('waitMinutes', $dep);
        $this->assertArrayHasKey('isRealtime', $dep);
    }
}

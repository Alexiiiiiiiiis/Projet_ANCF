<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AlertControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListAlertsReturnsAll(): void
    {
        $this->client->request('GET', '/api/alerts');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('fetchedAt', $data);
        $this->assertIsArray($data['alerts']);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testListAlertsFilterBySeverity(): void
    {
        $this->client->request('GET', '/api/alerts?severity=MAJOR');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        foreach ($data['alerts'] as $alert) {
            $this->assertSame('MAJOR', $alert['severity']);
        }
    }

    public function testListAlertsFilterByType(): void
    {
        $this->client->request('GET', '/api/alerts?type=METRO');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        foreach ($data['alerts'] as $alert) {
            $this->assertSame('METRO', $alert['transportType']);
        }
    }

    public function testAlertsByLineReturnsData(): void
    {
        $this->client->request('GET', '/api/alerts/M1');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('lineId', $data);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertSame('M1', $data['lineId']);
    }
}

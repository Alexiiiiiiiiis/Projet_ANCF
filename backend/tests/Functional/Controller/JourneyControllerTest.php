<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class JourneyControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testSearchRequiresFromAndTo(): void
    {
        $this->client->request('GET', '/api/journeys');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchRejectsSameFromAndTo(): void
    {
        $this->client->request('GET', '/api/journeys?from=stop_area:IDFM:71264&to=stop_area:IDFM:71264');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchReturnsJourneys(): void
    {
        $this->client->request('GET', '/api/journeys?from=stop_area:IDFM:71264&to=stop_area:IDFM:71517');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('journeys', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertSame('stop_area:IDFM:71264', $data['from']);
        $this->assertSame('stop_area:IDFM:71517', $data['to']);
        $this->assertGreaterThan(0, $data['count']);
    }
}

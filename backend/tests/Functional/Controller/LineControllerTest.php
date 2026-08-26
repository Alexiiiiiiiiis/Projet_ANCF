<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LineControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListReturnsLinesOfTheMode(): void
    {
        $this->client->request('GET', '/api/lines?type=METRO');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('METRO', $data['type']);
        $this->assertNotEmpty($data['lines']);
        foreach ($data['lines'] as $line) {
            $this->assertSame('METRO', $line['transportType']);
            $this->assertArrayHasKey('code', $line);
            $this->assertArrayHasKey('label', $line);
            $this->assertArrayHasKey('lineCode', $line);
        }
    }

    public function testListRejectsUnknownMode(): void
    {
        $this->client->request('GET', '/api/lines?type=trottinette');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testListIsPublic(): void
    {
        // Le catalogue des lignes est de l information de service : pas de compte exige.
        $this->client->request('GET', '/api/lines?type=RER');
        $this->assertResponseIsSuccessful();
    }

    public function testStopsReturnsTheLineAndItsStops(): void
    {
        $this->client->request('GET', '/api/lines/line:IDFM:C01742/stops');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('RER A', $data['line']['lineCode']);
        $this->assertNotEmpty($data['stops']);
        foreach ($data['stops'] as $stop) {
            $this->assertContains('RER A', $stop['lines']);
        }
    }

    public function testStatusReturnsOneEntryPerLine(): void
    {
        $this->client->request('GET', '/api/lines/status?ids=line:IDFM:C01742,line:IDFM:C01743');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['count']);
        $this->assertSame(
            ['line:IDFM:C01742', 'line:IDFM:C01743'],
            array_column($data['statuses'], 'lineId')
        );
        foreach ($data['statuses'] as $statut) {
            $this->assertContains($statut['severity'], ['NORMAL', 'INFO', 'MODERATE', 'MAJOR']);
        }
    }

    public function testStatusRequiresIds(): void
    {
        $this->client->request('GET', '/api/lines/status');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testStopsRejectsUnknownLine(): void
    {
        $this->client->request('GET', '/api/lines/line:IDFM:inconnue/stops');
        $this->assertResponseStatusCodeSame(404);
    }
}

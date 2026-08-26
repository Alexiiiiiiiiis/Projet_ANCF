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

    public function testDeparturesAcceptsTypeFilter(): void
    {
        $this->client->request('GET', '/api/schedules/stop:M14:chatelet?type=metro');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('METRO', $data['type']);
        foreach ($data['departures'] as $departure) {
            $this->assertSame('METRO', $departure['transportType']);
        }
    }

    public function testDeparturesIgnoresUnknownTypeFilter(): void
    {
        // Un mode inconnu ne doit pas vider la liste ni renvoyer une erreur : on l'ignore.
        $this->client->request('GET', '/api/schedules/stop:M14:chatelet?type=trottinette');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNull($data['type']);
        $this->assertNotEmpty($data['departures']);
    }

    public function testDeparturesAcceptsLineFilter(): void
    {
        // Gare du Nord voit passer le RER B et le RER D : filtrer sur l'un ne doit pas laisser
        // passer l'autre, meme mode ou pas.
        $this->client->request('GET', '/api/schedules/stop:RERA:gare_nord?line=RER%20B');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('RER B', $data['line']);
        $this->assertNotEmpty($data['departures']);
        foreach ($data['departures'] as $departure) {
            $this->assertSame('RER B', $departure['lineCode']);
        }
    }

    public function testDeparturesListsEveryLineOfTheStopWhateverTheFilter(): void
    {
        // Les boutons de filtre du client sont construits a partir de « lines » : ils doivent
        // rester au complet une fois une ligne selectionnee, sinon on ne peut plus en changer.
        $this->client->request('GET', '/api/schedules/stop:RERA:gare_nord?line=RER%20B');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(['RER B', 'RER D'], array_column($data['lines'], 'lineCode'));
    }

    public function testDeparturesLimitRaisesTheNumberOfResults(): void
    {
        $this->client->request('GET', '/api/schedules/stop:RERA:gare_nord');
        $parDefaut = json_decode($this->client->getResponse()->getContent(), true)['departures'];

        $this->client->request('GET', '/api/schedules/stop:RERA:gare_nord?limit=40');
        $this->assertResponseIsSuccessful();
        $complet = json_decode($this->client->getResponse()->getContent(), true)['departures'];

        $this->assertCount(5, $parDefaut);
        $this->assertGreaterThan(count($parDefaut), count($complet));
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

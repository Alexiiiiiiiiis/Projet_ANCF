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

    /**
     * Crée un compte jetable et renvoie son jeton : le calcul d'itinéraire est réservé aux
     * membres (cf. security.yaml), un visiteur reçoit 401.
     */
    private function authentifier(): string
    {
        $email = 'journey_'.uniqid().'@ancf.fr';
        $motDePasse = 'Password123!';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $motDePasse, 'firstName' => 'Test', 'lastName' => 'User'])
        );
        $this->assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/auth/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $motDePasse])
        );

        return json_decode($this->client->getResponse()->getContent(), true)['token'];
    }

    /** @param array<string, string> $query */
    private function demanderItineraire(string $token, array $query): void
    {
        $this->client->request(
            'GET',
            '/api/journeys?'.http_build_query($query),
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );
    }

    public function testSearchRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/journeys?from=stop_area:IDFM:71264&to=stop_area:IDFM:71517');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testSearchRequiresFromAndTo(): void
    {
        $token = $this->authentifier();

        $this->demanderItineraire($token, []);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchRejectsSameFromAndTo(): void
    {
        $token = $this->authentifier();

        $this->demanderItineraire($token, ['from' => 'stop_area:IDFM:71264', 'to' => 'stop_area:IDFM:71264']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testSearchReturnsJourneys(): void
    {
        $token = $this->authentifier();

        $this->demanderItineraire($token, ['from' => 'stop_area:IDFM:71264', 'to' => 'stop_area:IDFM:71517']);
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('journeys', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertSame('stop_area:IDFM:71264', $data['from']);
        $this->assertSame('stop_area:IDFM:71517', $data['to']);
        $this->assertGreaterThan(0, $data['count']);
    }
}

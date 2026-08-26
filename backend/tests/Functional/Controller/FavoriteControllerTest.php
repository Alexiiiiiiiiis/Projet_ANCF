<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FavoriteControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testListFavoritesRequiresAuth(): void
    {
        $this->client->request('GET', '/api/favorites');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAddFavoriteRequiresAuth(): void
    {
        $this->client->request(
            'POST',
            '/api/favorites',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'stopId' => 'stop:M14:chatelet',
                'stopName' => 'Châtelet',
                'lineCode' => 'M14',
                'transportType' => 'METRO',
            ])
        );
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteFavoriteRequiresAuth(): void
    {
        $this->client->request('DELETE', '/api/favorites/1');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testReorderFavoriteRequiresAuth(): void
    {
        $this->client->request(
            'PUT',
            '/api/favorites/1/reorder',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['sortOrder' => 1])
        );
        $this->assertResponseStatusCodeSame(401);
    }

    private function authenticate(): array
    {
        $email = 'fav_'.uniqid().'@ancf.fr';
        $password = 'Password123!';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password, 'firstName' => 'Test', 'lastName' => 'User'])
        );
        $this->assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/auth/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        return ['email' => $email, 'token' => $token];
    }

    public function testAddFavoriteRejectsNonStringFields(): void
    {
        ['token' => $token] = $this->authenticate();

        $this->client->request(
            'POST',
            '/api/favorites',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode([
                'stopId' => ['not', 'a', 'string'],
                'stopName' => 'Châtelet',
                'lineCode' => 'M14',
                'transportType' => 'METRO',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testAddFavoriteAcceptsStopWithoutLine(): void
    {
        ['token' => $token] = $this->authenticate();

        // La recherche de proximité renvoie des arrêts sans aucune ligne rattachée : le frontend
        // envoie alors un lineCode vide, qui doit rester acceptable.
        $this->client->request(
            'POST',
            '/api/favorites',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode([
                'stopId' => 'stop_area:IDFM:73797',
                'stopName' => 'Tour Eiffel',
                'lineCode' => '',
                'transportType' => 'BUS',
            ])
        );

        $this->assertResponseStatusCodeSame(201);
    }

    public function testReorderFavoriteRejectsOutOfRangeSortOrder(): void
    {
        ['token' => $token] = $this->authenticate();

        $this->client->request(
            'POST',
            '/api/favorites',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode([
                'stopId' => 'stop:M14:chatelet',
                'stopName' => 'Châtelet',
                'lineCode' => 'M14',
                'transportType' => 'METRO',
            ])
        );
        $this->assertResponseStatusCodeSame(201);
        $favoriteId = json_decode($this->client->getResponse()->getContent(), true)['id'];

        $this->client->request(
            'PUT',
            "/api/favorites/{$favoriteId}/reorder",
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['sortOrder' => 999999999])
        );

        $this->assertResponseStatusCodeSame(400);
    }
}

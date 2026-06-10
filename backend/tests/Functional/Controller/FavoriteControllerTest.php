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
}

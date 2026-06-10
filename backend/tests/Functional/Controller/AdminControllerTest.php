<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testStatsRequiresAuth(): void
    {
        $this->client->request('GET', '/api/admin/stats');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testUsersListRequiresAuth(): void
    {
        $this->client->request('GET', '/api/admin/users');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testToggleUserRequiresAuth(): void
    {
        $this->client->request('PUT', '/api/admin/users/1/toggle');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiLogsRequiresAuth(): void
    {
        $this->client->request('GET', '/api/admin/api-logs');
        $this->assertResponseStatusCodeSame(401);
    }
}

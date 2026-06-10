<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function cleanUpUser(string $email): void
    {
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $em->remove($user);
            $em->flush();
        }
    }

    public function testRegisterSuccess(): void
    {
        $email = 'test_' . uniqid() . '@ancf.fr';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'     => $email,
                'password'  => 'Password123!',
                'firstName' => 'Test',
                'lastName'  => 'User',
            ])
        );

        $response = $this->client->getResponse();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($email, $data['email']);
        $this->assertSame('Test', $data['firstName']);

        $this->cleanUpUser($email);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $email = 'duplicate_' . uniqid() . '@ancf.fr';

        // First registration
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'     => $email,
                'password'  => 'Password123!',
                'firstName' => 'Test',
                'lastName'  => 'User',
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        // Second registration — should fail
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'     => $email,
                'password'  => 'Password123!',
                'firstName' => 'Test2',
                'lastName'  => 'User2',
            ])
        );
        $this->assertResponseStatusCodeSame(409);

        $this->cleanUpUser($email);
    }

    public function testRegisterShortPassword(): void
    {
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email'     => 'short@ancf.fr',
                'password'  => '123',
                'firstName' => 'Test',
                'lastName'  => 'User',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMeWithoutAuth(): void
    {
        $this->client->request('GET', '/api/auth/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testStopsSearchPublic(): void
    {
        $this->client->request('GET', '/api/stops/search?q=Chatelet');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('stops', $data);
    }

    public function testStopsSearchTooShort(): void
    {
        $this->client->request('GET', '/api/stops/search?q=A');
        $this->assertResponseStatusCodeSame(400);
    }

    public function testAlertsPublic(): void
    {
        $this->client->request('GET', '/api/alerts');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('alerts', $data);
        $this->assertIsArray($data['alerts']);
    }

    public function testFavoritesRequiresAuth(): void
    {
        $this->client->request('GET', '/api/favorites');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testAdminRequiresAdminRole(): void
    {
        $this->client->request('GET', '/api/admin/stats');
        $this->assertResponseStatusCodeSame(401);
    }
}

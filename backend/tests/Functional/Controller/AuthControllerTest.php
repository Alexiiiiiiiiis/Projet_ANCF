<?php

namespace App\Tests\Functional\Controller;

use App\Entity\SearchHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $email = 'test_'.uniqid().'@ancf.fr';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'Password123!',
                'firstName' => 'Test',
                'lastName' => 'User',
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
        $email = 'duplicate_'.uniqid().'@ancf.fr';

        // First registration
        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'Password123!',
                'firstName' => 'Test',
                'lastName' => 'User',
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
                'email' => $email,
                'password' => 'Password123!',
                'firstName' => 'Test2',
                'lastName' => 'User2',
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
                'email' => 'short@ancf.fr',
                'password' => '123',
                'firstName' => 'Test',
                'lastName' => 'User',
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

    public function testResetPasswordTokenIsHashedInDatabaseAndWorksEndToEnd(): void
    {
        $email = 'reset_'.uniqid().'@ancf.fr';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => 'Password123!',
                'firstName' => 'Test',
                'lastName' => 'User',
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        $this->client->request(
            'POST',
            '/api/auth/forgot-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email])
        );
        $this->assertResponseIsSuccessful();
        $forgotData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('debug_token', $forgotData, 'Le token en clair doit rester disponible en dev pour les tests');

        $rawToken = $forgotData['debug_token'];

        // Le token stocké en base ne doit jamais être le token en clair
        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertNotSame($rawToken, $user->getResetPasswordToken());
        $this->assertSame(hash('sha256', $rawToken), $user->getResetPasswordToken());

        // Mais le token en clair doit tout de même permettre de réinitialiser le mot de passe
        $this->client->request(
            'POST',
            '/api/auth/reset-password',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['token' => $rawToken, 'newPassword' => 'NewPassword456!'])
        );
        $this->assertResponseIsSuccessful();

        $this->cleanUpUser($email);
    }

    public function testDeleteAccountAnonymizesSearchHistoryInsteadOfDeletingIt(): void
    {
        $email = 'delete_'.uniqid().'@ancf.fr';
        $password = 'Password123!';

        $this->client->request(
            'POST',
            '/api/auth/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => $email,
                'password' => $password,
                'firstName' => 'Test',
                'lastName' => 'User',
            ])
        );
        $this->assertResponseStatusCodeSame(201);

        $em = $this->getEntityManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        $history = (new SearchHistory())->setUser($user)->setSearchQuery('Châtelet')->setResultCount(3);
        $em->persist($history);
        $em->flush();
        $historyId = $history->getId();

        $this->client->request(
            'POST',
            '/api/auth/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => $email, 'password' => $password])
        );
        $this->assertResponseIsSuccessful();
        $token = json_decode($this->client->getResponse()->getContent(), true)['token'];

        $this->client->request(
            'DELETE',
            '/api/auth/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['password' => $password])
        );
        $this->assertResponseIsSuccessful();

        $em->clear(); // Vide l'identity map pour forcer une relecture depuis la base
        $survivingHistory = $em->getRepository(SearchHistory::class)->find($historyId);

        $this->assertNotNull($survivingHistory, 'La suppression du compte ne doit pas détruire l\'historique de recherche');
        $this->assertNull($survivingHistory->getUser(), 'Le lien vers l\'utilisateur supprimé doit être anonymisé (NULL), pas laissé en cascade de suppression');

        // Nettoyage : l'historique orphelin n'a plus de propriétaire pour le supprimer via cleanUpUser
        $em->remove($survivingHistory);
        $em->flush();
    }

    public function testDeleteAccountRejectsWrongPassword(): void
    {
        $email = 'delpwd_'.uniqid().'@ancf.fr';
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

        // Mauvais mot de passe : le compte ne doit pas être supprimé
        $this->client->request(
            'DELETE',
            '/api/auth/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['password' => 'MauvaisMotDePasse!'])
        );
        $this->assertResponseStatusCodeSame(400);

        $em = $this->getEntityManager();
        $this->assertNotNull(
            $em->getRepository(User::class)->findOneBy(['email' => $email]),
            'Le compte ne doit pas être supprimé si le mot de passe de confirmation est incorrect'
        );

        // Bon mot de passe : le compte doit être supprimé
        $this->client->request(
            'DELETE',
            '/api/auth/account',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            json_encode(['password' => $password])
        );
        $this->assertResponseIsSuccessful();

        $em->clear();
        $this->assertNull($em->getRepository(User::class)->findOneBy(['email' => $email]));
    }
}

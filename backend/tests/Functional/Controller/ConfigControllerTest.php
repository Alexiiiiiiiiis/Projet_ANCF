<?php

namespace App\Tests\Functional\Controller;

use App\Entity\SystemParameter;
use App\Service\SystemParameters;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConfigControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        // Le mode maintenance couperait toutes les routes des tests suivants s'il restait actif.
        $this->setParameter('maintenance_mode', 'false');

        parent::tearDown();
    }

    /** Écrit un paramètre système et invalide son cache, comme le fait l'espace d'administration. */
    private function setParameter(string $key, string $value): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $repository = $em->getRepository(SystemParameter::class);

        $parameter = $repository->findOneBy(['paramKey' => $key]);
        if (!$parameter) {
            $parameter = (new SystemParameter())
                ->setParamKey($key)
                ->setLabel($key)
                ->setType('boolean');
            $em->persist($parameter);
        }

        $parameter->setParamValue($value);
        $em->flush();

        static::getContainer()->get(SystemParameters::class)->invalidate();
    }

    public function testConfigIsPublicAndExposesTheClientSettings(): void
    {
        $this->client->request('GET', '/api/config');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        foreach (['refreshInterval', 'defaultRadius', 'maxFavorites', 'maintenanceMode'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertIsInt($data['refreshInterval']);
        $this->assertIsBool($data['maintenanceMode']);
    }

    /** Les TTL de cache sont une affaire de serveur : les publier n'apprendrait rien au navigateur. */
    public function testConfigDoesNotLeakServerSideCacheSettings(): void
    {
        $this->client->request('GET', '/api/config');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayNotHasKey('departuresCacheTtl', $data);
        $this->assertArrayNotHasKey('alertsCacheTtl', $data);
    }

    public function testMaintenanceModeClosesThePublicRoutes(): void
    {
        $this->setParameter('maintenance_mode', 'true');

        $this->client->request('GET', '/api/stops/search?q=chatelet');
        $this->assertResponseStatusCodeSame(503);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['maintenance']);
    }

    /**
     * La sonde doit continuer de répondre : l'hébergeur retirerait l'instance du routage sinon,
     * et le site deviendrait injoignable au lieu d'afficher une page de maintenance.
     */
    public function testMaintenanceModeKeepsTheHealthProbeOpen(): void
    {
        $this->setParameter('maintenance_mode', 'true');

        $this->client->request('GET', '/api/health');
        $this->assertResponseIsSuccessful();
    }

    /** Sans ça, le frontend ne pourrait pas savoir qu'il doit afficher l'écran d'attente. */
    public function testMaintenanceModeKeepsTheConfigRouteOpen(): void
    {
        $this->setParameter('maintenance_mode', 'true');

        $this->client->request('GET', '/api/config');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['maintenanceMode']);
    }

    /** Un administrateur déconnecté doit pouvoir se reconnecter pour ressortir du mode. */
    public function testMaintenanceModeKeepsTheLoginRouteOpen(): void
    {
        $this->setParameter('maintenance_mode', 'true');

        $this->client->request(
            'POST',
            '/api/auth/login_check',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => 'inconnu@ancf.fr', 'password' => 'faux'])
        );

        // Peu importe l'issue de l'authentification : ce qui compte est que la route réponde
        // elle-même, et ne soit pas interceptée par le mode maintenance.
        $this->assertNotSame(503, $this->client->getResponse()->getStatusCode());
    }

    public function testRoutesReopenOnceMaintenanceIsLifted(): void
    {
        $this->setParameter('maintenance_mode', 'true');
        $this->client->request('GET', '/api/stops/search?q=chatelet');
        $this->assertResponseStatusCodeSame(503);

        $this->setParameter('maintenance_mode', 'false');
        $this->client->request('GET', '/api/stops/search?q=chatelet');
        $this->assertResponseIsSuccessful();
    }
}

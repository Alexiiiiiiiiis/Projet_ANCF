<?php

namespace App\Tests\Unit\Service;

use App\Entity\SystemParameter;
use App\Repository\SystemParameterRepository;
use App\Service\SystemParameters;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class SystemParametersTest extends TestCase
{
    /** @param array<string, string> $rows */
    private function createService(array $rows, ?SystemParameterRepository $repository = null): SystemParameters
    {
        if (null === $repository) {
            $parameters = [];
            foreach ($rows as $key => $value) {
                $parameters[] = (new SystemParameter())->setParamKey($key)->setParamValue($value);
            }

            $repository = $this->createMock(SystemParameterRepository::class);
            $repository->method('findAll')->willReturn($parameters);
        }

        return new SystemParameters($repository, new ArrayAdapter());
    }

    public function testReadsAnIntegerFromTheDatabase(): void
    {
        $service = $this->createService(['departures_cache_ttl' => '120']);

        $this->assertSame(120, $service->getInt('departures_cache_ttl', 30, 5, 300));
    }

    public function testFallsBackToTheDefaultWhenTheParameterIsMissing(): void
    {
        $service = $this->createService([]);

        $this->assertSame(30, $service->getInt('departures_cache_ttl', 30, 5, 300));
    }

    public function testFallsBackToTheDefaultWhenTheValueIsNotNumeric(): void
    {
        $service = $this->createService(['departures_cache_ttl' => 'plus tard']);

        $this->assertSame(30, $service->getInt('departures_cache_ttl', 30, 5, 300));
    }

    /**
     * L'administration accepte n'importe quel nombre : sans plancher, un TTL à 0 ferait repartir
     * chaque requête vers l'API IDFM et épuiserait le quota quotidien en quelques minutes.
     */
    public function testClampsAValueBelowTheMinimum(): void
    {
        $service = $this->createService(['departures_cache_ttl' => '0']);

        $this->assertSame(5, $service->getInt('departures_cache_ttl', 30, 5, 300));
    }

    public function testClampsAValueAboveTheMaximum(): void
    {
        $service = $this->createService(['departures_cache_ttl' => '99999']);

        $this->assertSame(300, $service->getInt('departures_cache_ttl', 30, 5, 300));
    }

    public function testReadsBooleansStoredAsStrings(): void
    {
        $this->assertTrue($this->createService(['maintenance_mode' => 'true'])->getBool('maintenance_mode', false));
        $this->assertFalse($this->createService(['maintenance_mode' => 'false'])->getBool('maintenance_mode', true));
        $this->assertTrue($this->createService(['maintenance_mode' => 'oui'])->getBool('maintenance_mode', true));
        $this->assertFalse($this->createService([])->getBool('maintenance_mode', false));
    }

    /**
     * Une base injoignable ne doit pas propager son erreur : toutes les routes publiques
     * consultent ce service, une exception ici les mettrait toutes en 500.
     */
    public function testReturnsDefaultsWhenTheRepositoryFails(): void
    {
        $repository = $this->createMock(SystemParameterRepository::class);
        $repository->method('findAll')->willThrowException(new \RuntimeException('base injoignable'));

        $service = $this->createService([], $repository);

        $this->assertSame(30, $service->getInt('departures_cache_ttl', 30, 5, 300));
        $this->assertFalse($service->getBool('maintenance_mode', false));
    }

    /** Les paramètres ne sont lus qu'une fois, même consultés plusieurs fois dans la même requête. */
    public function testReadsTheDatabaseOnlyOnce(): void
    {
        $repository = $this->createMock(SystemParameterRepository::class);
        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([(new SystemParameter())->setParamKey('max_favorites')->setParamValue('20')]);

        $service = $this->createService([], $repository);

        $service->getInt('max_favorites', 10);
        $service->getInt('max_favorites', 10);
        $service->getBool('maintenance_mode', false);
    }
}

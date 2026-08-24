<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/health')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Sonde utilisée par l'hébergeur pour savoir si l'instance est prête à recevoir du
     * trafic : renvoie 503 tant que la base n'est pas jointe, ce qui évite de router des
     * requêtes vers un conteneur démarré avant sa base de données.
     */
    #[Route('', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            $database = 'up';
        } catch (\Throwable) {
            $database = 'down';
        }

        $healthy = 'up' === $database;

        return new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $database,
            'time' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE);
    }
}

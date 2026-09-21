<?php

namespace App\EventListener;

use App\Entity\ApiLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::TERMINATE)]
class ApiLogListener
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Enregistre chaque requête /api/* (route, code HTTP, temps de réponse) une fois la réponse envoyée. */
    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // On ne journalise que les requêtes /api/
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // On exclut la route de connexion pour ne jamais enregistrer d'identifiants
        if ('/api/auth/login_check' === $path) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Temps de réponse calculé depuis le début de la requête PHP
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $responseTimeMs = null !== $startTime
            ? (int) round((microtime(true) - (float) $startTime) * 1000)
            : 0;

        // Plafonné au maximum d'un SMALLINT UNSIGNED (65535 ms ≈ 65 s)
        $responseTimeMs = min($responseTimeMs, 65535);

        $errorMessage = null;
        if ($statusCode >= 400) {
            $content = $response->getContent();
            if (false !== $content && '' !== $content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $errorMessage = $decoded['message'] ?? $decoded['detail'] ?? $decoded['error'] ?? null;
                }
                // Sans message structuré, on garde le contenu brut (tronqué)
                if (null === $errorMessage) {
                    $errorMessage = mb_substr($content, 0, 500);
                }
            }
        }

        $log = (new ApiLog())
            ->setEndpoint($path)
            ->setHttpMethod($request->getMethod())
            ->setStatusCode($statusCode)
            ->setResponseTimeMs($responseTimeMs)
            ->setErrorMessage($errorMessage);

        try {
            $this->entityManager->persist($log);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // La journalisation ne doit jamais faire planter l'application
        }
    }
}

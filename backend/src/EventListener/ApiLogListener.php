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

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only log /api/ requests
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Exclude the login endpoint to avoid logging credentials
        if ('/api/auth/login_check' === $path) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Calculate response time from the PHP request start time
        $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $responseTimeMs = null !== $startTime
            ? (int) round((microtime(true) - (float) $startTime) * 1000)
            : 0;

        // Cap at SMALLINT UNSIGNED max (65535 ms ≈ 65 s)
        $responseTimeMs = min($responseTimeMs, 65535);

        $errorMessage = null;
        if ($statusCode >= 400) {
            $content = $response->getContent();
            if (false !== $content && '' !== $content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $errorMessage = $decoded['message'] ?? $decoded['detail'] ?? $decoded['error'] ?? null;
                }
                // Fall back to raw content (capped) if no structured message found
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
            // Never let logging break the application
        }
    }
}

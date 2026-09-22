<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\SystemParameters;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Coupe l'API quand le mode maintenance est activé depuis l'administration.
 *
 * Priorité 5 : le pare-feu de sécurité s'exécute à 8 sur le même événement, donc le jeton JWT est
 * déjà résolu ici — sans quoi isGranted() répondrait toujours non et enfermerait aussi les
 * administrateurs dehors.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final class MaintenanceListener
{
    /**
     * Routes qui répondent même en maintenance :
     * - /api/health : la sonde de l'hébergeur, qui sortirait l'instance du routage sinon
     * - /api/auth/login_check : sans elle, un administrateur déconnecté ne pourrait plus se
     *   reconnecter pour ressortir du mode maintenance
     * - /api/admin : l'écran qui porte justement l'interrupteur
     * - /api/config : le frontend y lit l'état pour afficher une page d'attente plutôt qu'une erreur
     */
    private const ALWAYS_OPEN = [
        '/api/health',
        '/api/auth/login_check',
        '/api/admin',
        '/api/config',
    ];

    public function __construct(
        private readonly SystemParameters $parameters,
        private readonly Security $security,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (!str_starts_with($path, '/api/')) {
            return;
        }

        foreach (self::ALWAYS_OPEN as $open) {
            if (str_starts_with($path, $open)) {
                return;
            }
        }

        if (!$this->parameters->getBool('maintenance_mode', false)) {
            return;
        }

        // Les administrateurs continuent de circuler : c'est la seule façon de vérifier que
        // l'application fonctionne avant de rouvrir au public.
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'Service temporairement indisponible pour maintenance.',
            'maintenance' => true,
        ], Response::HTTP_SERVICE_UNAVAILABLE));
    }
}

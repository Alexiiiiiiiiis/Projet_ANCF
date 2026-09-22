<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SystemParameters;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Paramètres système destinés au client web.
 *
 * Seules les valeurs que le frontend doit connaître sont exposées : les TTL de cache restent une
 * affaire de serveur, les publier n'apprendrait rien d'utile au navigateur. Route publique, parce
 * qu'un visiteur non connecté consulte les horaires et doit donc connaître leur cadence de
 * rafraîchissement.
 */
#[Route('/api/config')]
class ConfigController extends AbstractController
{
    #[Route('', name: 'config_index', methods: ['GET'])]
    public function index(SystemParameters $parameters): JsonResponse
    {
        return $this->json([
            'refreshInterval' => $parameters->getInt('refresh_interval', 30, 5, 600),
            'defaultRadius' => $parameters->getInt('default_radius', 500, 100, 2000),
            'maxFavorites' => $parameters->getInt('max_favorites', 20, 1, 200),
            'maintenanceMode' => $parameters->getBool('maintenance_mode', false),
        ]);
    }
}

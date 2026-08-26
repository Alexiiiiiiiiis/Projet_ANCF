<?php

namespace App\Controller;

use App\Service\IdfmApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Parcours de la page Horaires : on choisit un mode, puis une ligne, puis un de ses arrêts.
 */
#[Route('/api/lines')]
class LineController extends AbstractController
{
    public function __construct(
        private readonly IdfmApiService $idfmApi,
    ) {
    }

    #[Route('', name: 'lines_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $type = strtoupper(trim($request->query->getString('type', 'RER')));

        if (!\in_array($type, IdfmApiService::lineTypes(), true)) {
            return $this->json([
                'error' => 'Mode inconnu.',
                'accepted' => IdfmApiService::lineTypes(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $lines = $this->idfmApi->getLines($type);

        return $this->json([
            'type' => $type,
            'count' => count($lines),
            'lines' => $lines,
        ]);
    }

    #[Route('/{lineId}/stops', name: 'line_stops', methods: ['GET'])]
    public function stops(string $lineId): JsonResponse
    {
        $line = $this->idfmApi->getLine($lineId);

        if (null === $line) {
            return $this->json(['error' => 'Ligne inconnue.'], Response::HTTP_NOT_FOUND);
        }

        $stops = $this->idfmApi->getLineStops($lineId);

        return $this->json([
            'line' => $line,
            'count' => count($stops),
            'stops' => $stops,
        ]);
    }
}

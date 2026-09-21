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

    /** GET /api/lines?type= : toutes les lignes d'un mode (METRO, RER, TRAM ou BUS). */
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

    /** Nombre de lignes interrogeables d'un coup : au-delà, la page n'affiche plus de pastilles. */
    private const STATUS_MAX_LINES = 20;

    /**
     * État de trafic de plusieurs lignes à la fois — la pastille des lignes favorites. Une
     * requête par ligne ferait autant d'allers-retours que de favoris pour trois mots de
     * réponse chacun.
     */
    #[Route('/status', name: 'lines_status', methods: ['GET'])]
    public function status(Request $request): JsonResponse
    {
        $ids = array_filter(array_map('trim', explode(',', $request->query->getString('ids', ''))));

        if ([] === $ids) {
            return $this->json(['error' => 'Paramètre ids requis.'], Response::HTTP_BAD_REQUEST);
        }

        $statuses = array_map(
            fn (string $lineId) => $this->idfmApi->getLineTrafficStatus($lineId),
            array_slice(array_values(array_unique($ids)), 0, self::STATUS_MAX_LINES)
        );

        return $this->json([
            'count' => count($statuses),
            'statuses' => $statuses,
        ]);
    }

    /** GET /api/lines/{lineId}/stops : infos d'une ligne et liste de ses arrêts. */
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

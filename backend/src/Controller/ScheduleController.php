<?php

namespace App\Controller;

use App\Service\IdfmApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/schedules')]
class ScheduleController extends AbstractController
{
    public function __construct(
        private readonly IdfmApiService $idfmApi,
    ) {
    }

    /** Modes acceptés par ?type= ; toute autre valeur est ignorée plutôt que rejetée. */
    private const TYPES = ['METRO', 'RER', 'TRAM', 'BUS'];

    #[Route('/{stopId}', name: 'schedules_departures', methods: ['GET'])]
    public function departures(string $stopId, Request $request): JsonResponse
    {
        $type = strtoupper(trim($request->query->getString('type', '')));
        $type = \in_array($type, self::TYPES, true) ? $type : null;

        // ?line=RER B — filtre par ligne précise, pour ne voir à Gare du Nord que les passages
        // du RER B et pas ceux des métros. Aucune validation de liste : les codes viennent
        // d'IDFM, un code inconnu renvoie simplement une liste vide.
        $line = trim($request->query->getString('line', ''));
        $line = '' !== $line ? $line : null;

        $limit = $request->query->has('limit') ? $request->query->getInt('limit') : null;

        $departures = $this->idfmApi->getNextDepartures($stopId, $type, $line, $limit);

        return $this->json([
            'stopId' => $stopId,
            'type' => $type,
            'line' => $line,
            'fetchedAt' => date('c'),
            'refreshInterval' => 30,
            // Toutes les lignes de l'arrêt, filtres non appliqués : c'est ce qui permet au
            // client d'afficher les boutons de filtre sans qu'ils disparaissent une fois l'un
            // d'eux sélectionné.
            'lines' => $this->idfmApi->getDepartureLines($stopId),
            'departures' => $departures,
        ]);
    }
}

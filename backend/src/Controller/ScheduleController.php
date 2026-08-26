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

        $departures = $this->idfmApi->getNextDepartures($stopId, $type);

        return $this->json([
            'stopId' => $stopId,
            'type' => $type,
            'fetchedAt' => date('c'),
            'refreshInterval' => 30,
            'departures' => $departures,
        ]);
    }
}

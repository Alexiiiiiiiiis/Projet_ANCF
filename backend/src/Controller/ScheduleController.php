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

    #[Route('/{stopId}', name: 'schedules_departures', methods: ['GET'])]
    public function departures(string $stopId, Request $request): JsonResponse
    {
        $departures = $this->idfmApi->getNextDepartures($stopId);

        return $this->json([
            'stopId' => $stopId,
            'fetchedAt' => date('c'),
            'refreshInterval' => 30,
            'departures' => $departures,
        ]);
    }
}

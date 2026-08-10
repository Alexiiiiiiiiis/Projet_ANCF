<?php

namespace App\Controller;

use App\Service\IdfmApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/journeys')]
class JourneyController extends AbstractController
{
    public function __construct(
        private readonly IdfmApiService $idfmApi,
    ) {
    }

    #[Route('', name: 'journeys_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $from = trim($request->query->getString('from', ''));
        $to = trim($request->query->getString('to', ''));
        $fromName = $request->query->get('fromName');
        $toName = $request->query->get('toName');

        if ('' === $from || '' === $to) {
            return $this->json(['error' => 'Les paramètres "from" et "to" sont requis.'], Response::HTTP_BAD_REQUEST);
        }

        if ($from === $to) {
            return $this->json(['error' => 'Le départ et l\'arrivée doivent être différents.'], Response::HTTP_BAD_REQUEST);
        }

        $journeys = $this->idfmApi->searchJourneys($from, $to, $fromName, $toName);

        return $this->json([
            'from' => $from,
            'to' => $to,
            'fetchedAt' => date('c'),
            'count' => count($journeys),
            'journeys' => $journeys,
        ]);
    }
}

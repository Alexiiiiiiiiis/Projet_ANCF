<?php

namespace App\Controller;

use App\Service\IdfmApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/alerts')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly IdfmApiService $idfmApi,
    ) {
    }

    #[Route('', name: 'alerts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $lineId = $request->query->get('lineId');
        $severity = $request->query->get('severity');
        $type = $request->query->get('type');
        $category = $request->query->get('category');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));

        $alerts = $this->idfmApi->getTrafficAlerts($lineId);

        // Filter by severity if provided
        if ($severity) {
            $alerts = array_filter($alerts, fn ($a) => $a['severity'] === strtoupper($severity));
        }

        // Filter by transport type if provided
        if ($type) {
            $alerts = array_filter($alerts, fn ($a) => $a['transportType'] === strtoupper($type));
        }

        // Filter by category (INCIDENT / TRAVAUX)
        if ($category) {
            $alerts = array_filter($alerts, fn ($a) => ($a['category'] ?? 'INCIDENT') === strtoupper($category));
        }

        $alerts = array_values($alerts);
        $totalCount = count($alerts);
        $totalPages = max(1, (int) ceil($totalCount / $limit));
        $page = min($page, $totalPages);

        return $this->json([
            'fetchedAt' => date('c'),
            'count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
            'alerts' => array_slice($alerts, ($page - 1) * $limit, $limit),
        ]);
    }

    #[Route('/{lineId}', name: 'alerts_by_line', methods: ['GET'])]
    public function byLine(string $lineId): JsonResponse
    {
        $alerts = $this->idfmApi->getTrafficAlerts($lineId);

        return $this->json([
            'lineId' => $lineId,
            'count' => count($alerts),
            'alerts' => $alerts,
        ]);
    }
}

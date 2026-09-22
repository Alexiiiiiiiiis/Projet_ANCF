<?php

namespace App\Controller;

use App\Entity\SearchHistory;
use App\Entity\User;
use App\Repository\SearchHistoryRepository;
use App\Service\IdfmApiService;
use App\Service\SystemParameters;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stops')]
class StopController extends AbstractController
{
    public function __construct(
        private readonly IdfmApiService $idfmApi,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly SystemParameters $parameters,
    ) {
    }

    /** GET /api/stops/search?q= : recherche d'arrêts par nom, enregistrée dans l'historique. */
    #[Route('/search', name: 'stops_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q', ''));
        $type = $request->query->get('type');
        $limit = min((int) $request->query->get('limit', 10), 20);

        if (strlen($query) < 2) {
            return $this->json(['error' => 'La requête doit contenir au moins 2 caractères.'], Response::HTTP_BAD_REQUEST);
        }

        $stops = $this->idfmApi->searchStops($query, $type, $limit);

        $user = $this->security->getUser();
        $history = (new SearchHistory())
            ->setSearchQuery($query)
            ->setUser($user instanceof User ? $user : null)
            ->setResultCount(count($stops));
        $this->em->persist($history);
        $this->em->flush();

        return $this->json([
            'query' => $query,
            'count' => count($stops),
            'stops' => $stops,
        ]);
    }

    /** GET /api/stops/nearby : arrêts autour d'une position GPS (lat, lon, radius). */
    #[Route('/nearby', name: 'stops_nearby', methods: ['GET'])]
    public function nearby(Request $request): JsonResponse
    {
        $lat = (float) $request->query->get('lat', 48.8566);
        $lon = (float) $request->query->get('lon', 2.3522);
        // Rayon par défaut réglable depuis l'administration ; le plafond de 2 km reste codé
        // ici, c'est une limite technique (coût de l'appel IDFM), pas une préférence.
        $defaultRadius = $this->parameters->getInt('default_radius', 500, 100, 2000);
        $radius = min((int) $request->query->get('radius', $defaultRadius), 2000);
        $type = $request->query->get('type');

        if (0.0 === $lat || 0.0 === $lon) {
            return $this->json(['error' => 'Coordonnées invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $stops = $this->idfmApi->getNearbyStops($lat, $lon, $radius, $type);

        return $this->json([
            'lat' => $lat,
            'lon' => $lon,
            'radius' => $radius,
            'count' => count($stops),
            'stops' => $stops,
        ]);
    }

    // F5.4 — dernières recherches de l'utilisateur connecté
    // (doit être déclarée avant la route attrape-tout /{stopId})
    #[Route('/history', name: 'stops_history', methods: ['GET'])]
    public function history(SearchHistoryRepository $historyRepo): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->json(['queries' => []]);
        }

        $queries = [];
        foreach ($historyRepo->findRecentByUser($user, 30) as $entry) {
            $query = $entry->getSearchQuery();
            $key = mb_strtolower($query);
            if (!isset($queries[$key])) {
                $queries[$key] = $query;
            }
            if (count($queries) >= 8) {
                break;
            }
        }

        return $this->json(['queries' => array_values($queries)]);
    }

    /** GET /api/stops/{stopId} : prochains départs d'un arrêt. */
    #[Route('/{stopId}', name: 'stops_detail', methods: ['GET'])]
    public function detail(string $stopId): JsonResponse
    {
        $departures = $this->idfmApi->getNextDepartures($stopId);

        return $this->json([
            'stopId' => $stopId,
            'departures' => $departures,
        ]);
    }
}

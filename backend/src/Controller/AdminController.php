<?php

namespace App\Controller;

use App\Entity\SystemParameter;
use App\Entity\User;
use App\Repository\ApiLogRepository;
use App\Repository\SystemParameterRepository;
use App\Repository\UserRepository;
use App\Repository\SearchHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepo,
        private readonly ApiLogRepository $apiLogRepo,
        private readonly SearchHistoryRepository $searchHistoryRepo,
        private readonly SystemParameterRepository $paramRepo,
    ) {}

    #[Route('/stats', name: 'admin_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalUsers    = $this->userRepo->countAll();
        $requestsToday = $this->searchHistoryRepo->countRequestsToday();
        $errorsToday   = $this->apiLogRepo->countErrorsToday();
        $avgResponse   = $this->apiLogRepo->getAverageResponseTime();

        return $this->json([
            'totalUsers'       => $totalUsers,
            'requestsPerDay'   => $requestsToday,
            'uptime'           => 99.8,
            'activeAlerts'     => 3,
            'errorsToday'      => $errorsToday,
            'avgResponseMs'    => round($avgResponse),
        ]);
    }

    #[Route('/users', name: 'admin_users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min((int) $request->query->get('limit', 20), 100);

        $users = $this->userRepo->findPaginated($page, $limit);
        $total = $this->userRepo->countAll();

        return $this->json([
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'users' => array_map(fn(User $u) => $u->toArray(), $users),
        ]);
    }

    #[Route('/users/{id}/toggle', name: 'admin_users_toggle', methods: ['PUT'])]
    public function toggleUser(int $id): JsonResponse
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return $this->json(['error' => 'Utilisateur non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $user->setIsActive(!$user->isActive());
        $this->em->flush();

        return $this->json([
            'id'       => $user->getId(),
            'isActive' => $user->isActive(),
            'message'  => $user->isActive() ? 'Compte activé.' : 'Compte bloqué.',
        ]);
    }

    #[Route('/api-logs', name: 'admin_api_logs', methods: ['GET'])]
    public function apiLogs(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 50), 200);
        $logs = $this->apiLogRepo->findRecent($limit);
        $avgMs = $this->apiLogRepo->getAverageResponseTime();

        return $this->json([
            'count'         => count($logs),
            'avgResponseMs' => round($avgMs),
            'apiStatus'     => $avgMs < 500 ? 'OK' : 'SLOW',
            'logs'          => array_map(fn($l) => $l->toArray(), $logs),
        ]);
    }

    #[Route('/parameters', name: 'admin_parameters_list', methods: ['GET'])]
    public function parameters(): JsonResponse
    {
        $params = $this->paramRepo->findAll();

        return $this->json([
            'parameters' => array_map(fn(SystemParameter $p) => $p->toArray(), $params),
        ]);
    }

    #[Route('/parameters/{id}', name: 'admin_parameters_update', methods: ['PUT'])]
    public function updateParameter(int $id, Request $request): JsonResponse
    {
        $param = $this->paramRepo->find($id);

        if (!$param) {
            return $this->json(['error' => 'Paramètre non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newValue = $data['value'] ?? null;

        if ($newValue === null) {
            return $this->json(['error' => 'Valeur requise.'], Response::HTTP_BAD_REQUEST);
        }

        $param->setParamValue((string) $newValue);
        $this->em->flush();

        return $this->json([
            'message' => 'Paramètre mis à jour.',
            'parameter' => $param->toArray(),
        ]);
    }
}

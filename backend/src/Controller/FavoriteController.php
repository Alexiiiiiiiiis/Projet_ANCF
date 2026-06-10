<?php

namespace App\Controller;

use App\Entity\FavoriteStop;
use App\Entity\User;
use App\Repository\FavoriteStopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/favorites')]
class FavoriteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FavoriteStopRepository $favoriteRepo,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', name: 'favorites_list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $favorites = $this->favoriteRepo->findByUser($user);

        return $this->json([
            'count' => count($favorites),
            'favorites' => array_map(fn(FavoriteStop $f) => $f->toArray(), $favorites),
        ]);
    }

    #[Route('', name: 'favorites_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Check duplicate
        $existing = $this->favoriteRepo->findOneByUserAndStop($user, $data['stopId'] ?? '');
        if ($existing) {
            return $this->json(['error' => 'Cet arrêt est déjà dans vos favoris.'], Response::HTTP_CONFLICT);
        }

        $maxOrder = $this->favoriteRepo->getMaxSortOrderForUser($user);

        $favorite = new FavoriteStop();
        $favorite->setUser($user)
                 ->setStopId($data['stopId'] ?? '')
                 ->setStopName($data['stopName'] ?? '')
                 ->setLineCode($data['lineCode'] ?? '')
                 ->setTransportType(strtoupper($data['transportType'] ?? 'BUS'))
                 ->setSortOrder($maxOrder + 1);

        $errors = $this->validator->validate($favorite);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->em->persist($favorite);
        $this->em->flush();

        return $this->json($favorite->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'favorites_delete', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $favorite = $this->favoriteRepo->find($id);

        if (!$favorite || $favorite->getUser() !== $user) {
            return $this->json(['error' => 'Favori non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($favorite);
        $this->em->flush();

        return $this->json(['message' => 'Favori supprimé.']);
    }

    #[Route('/{id}/reorder', name: 'favorites_reorder', methods: ['PUT'])]
    public function reorder(int $id, Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $favorite = $this->favoriteRepo->find($id);

        if (!$favorite || $favorite->getUser() !== $user) {
            return $this->json(['error' => 'Favori non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $newOrder = (int) ($data['sortOrder'] ?? 0);

        $favorite->setSortOrder($newOrder);
        $this->em->flush();

        return $this->json($favorite->toArray());
    }
}

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
    ) {
    }

    #[Route('', name: 'favorites_list', methods: ['GET'])]
    public function list(#[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $favorites = $this->favoriteRepo->findByUser($user);

        return $this->json([
            'count' => count($favorites),
            'favorites' => array_map(fn (FavoriteStop $f) => $f->toArray(), $favorites),
        ]);
    }

    #[Route('', name: 'favorites_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] ?User $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $stopId = $data['stopId'] ?? '';
        $stopName = $data['stopName'] ?? '';
        $lineCode = $data['lineCode'] ?? '';
        $transportType = $data['transportType'] ?? 'BUS';

        // Un champ non scalaire (ex. {"stopId": ["x"]}) ferait planter les setters typés
        // (string) ci-dessous avec une TypeError non interceptée → 500 au lieu d'un 400 propre.
        if (!is_string($stopId) || !is_string($stopName) || !is_string($lineCode) || !is_string($transportType)) {
            return $this->json(['error' => 'Champs invalides.'], Response::HTTP_BAD_REQUEST);
        }

        // Check duplicate
        $existing = $this->favoriteRepo->findOneByUserAndStop($user, $stopId);
        if ($existing) {
            return $this->json(['error' => 'Cet arrêt est déjà dans vos favoris.'], Response::HTTP_CONFLICT);
        }

        $maxOrder = $this->favoriteRepo->getMaxSortOrderForUser($user);

        $favorite = new FavoriteStop();
        $favorite->setUser($user)
                 ->setStopId($stopId)
                 ->setStopName($stopName)
                 ->setLineCode($lineCode)
                 ->setTransportType(strtoupper($transportType))
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
        $rawOrder = $data['sortOrder'] ?? 0;

        // sortOrder est stocké en SMALLINT (-32768..32767) : une valeur hors bornes
        // ferait planter le flush avec une exception DBAL non interceptée (500).
        if (!is_numeric($rawOrder) || (int) $rawOrder < -32768 || (int) $rawOrder > 32767) {
            return $this->json(['error' => 'sortOrder invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $favorite->setSortOrder((int) $rawOrder);
        $this->em->flush();

        return $this->json($favorite->toArray());
    }
}

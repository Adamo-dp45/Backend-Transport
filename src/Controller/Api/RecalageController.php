<?php

namespace App\Controller\Api;

use App\Domain\Service\RecalageService;
use App\Entity\User;
use App\Repository\LigneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Suggestions de RECALAGE des durées de tronçon d'une ligne (vue admin) : compare, tronçon par
 * tronçon, la durée déclarée à la médiane réellement observée sur les passages. Ne modifie RIEN —
 * l'application se fait à la main via le formulaire de ligne (cf. RecalageService).
 */
final class RecalageController extends AbstractController
{
    #[Route('/api/lignes/{id}/recalage', name: 'api_ligne_recalage', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(
        int $id,
        Request $request,
        Security $security,
        LigneRepository $ligneRepository,
        RecalageService $recalage
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();

        // Scope entreprise : on ne recale que SES lignes.
        $ligne = $ligneRepository->findOneBy(['id' => $id, 'identreprise' => $ent, 'deletedAt' => null]);
        if ($ligne === null) {
            throw new NotFoundHttpException('Ligne introuvable');
        }

        $minObservations = max(1, (int) $request->query->get('minObservations', 3));
        $suggestions = $recalage->suggestionsPourLigne($ligne, $minObservations);

        // Nb de tronçons pour lesquels une médiane fiable existe ET s'écarte du déclaré (aide de tri UI).
        $recalables = array_filter(
            $suggestions,
            static fn ($s) => $s['dureeMediane'] !== null && ($s['ecartMinutes'] === null || $s['ecartMinutes'] !== 0)
        );

        return new JsonResponse([
            'ligneId' => $ligne->getId(),
            'minObservations' => $minObservations,
            'nbTroncons' => count($suggestions),
            'nbRecalables' => count($recalables),
            'troncons' => $suggestions,
        ]);
    }
}

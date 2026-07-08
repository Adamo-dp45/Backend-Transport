<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tableau de bord de SA gare pour un utilisateur rattaché : recette + compteurs (billets/courriers/
 * bagages) sur une période (jour / mois / tout). Réutilise les 'recetteParGare' des repos (recette
 * encaissée à la gare : montée pour le billet, garedepart pour courrier/bagage), filtrés sur sa gare.
 * Aucune fuite : ne renvoie que les chiffres de la gare de l'utilisateur courant.
 */
final class GareDashboardController extends AbstractController
{
    #[Route('/api/gares/me/dashboard', name: 'api_gare_me_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request,
        Security $security,
        TicketRepository $ticketRepository,
        CourrierRepository $courrierRepository,
        BagageRepository $bagageRepository
    ): JsonResponse {
        // Recette = donnée financière : réservée à l'admin de gare (et admins entreprise/super).
        if (!$this->isGranted('ROLE_ADMIN_GARE') && !$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès à la recette de la gare réservé à l\'administrateur de gare.');
        }

        /** @var User $user */
        $user = $security->getUser();
        $gare = $user->getGare();
        if (!$gare) {
            return $this->json(['gare' => null]); // admin/central : pas de gare propre
        }
        $gareId = $gare->getId();
        $entId = $user->getEntreprise()->getId();

        $periode = $request->query->get('periode', 'mois');
        $periode = in_array($periode, ['jour', 'mois', 'tout'], true) ? $periode : 'mois';
        [$debut, $fin] = $this->intervalle($periode);

        $billets = $this->ligneGare($ticketRepository->recetteParGare($debut, $fin, $entId), $gareId);
        $courriers = $this->ligneGare($courrierRepository->recetteParGare($debut, $fin, $entId), $gareId);
        $bagages = $this->ligneGare($bagageRepository->recetteParGare($debut, $fin, $entId), $gareId);

        $rBillets = (int) ($billets['recette'] ?? 0);
        $rCourriers = (int) ($courriers['recette'] ?? 0);
        $rBagages = (int) ($bagages['recette'] ?? 0);

        return $this->json([
            'gare' => ['id' => $gareId, 'libelle' => $gare->getLibelle()],
            'periode' => $periode,
            'billets' => ['count' => (int) ($billets['nbtickets'] ?? 0), 'recette' => $rBillets],
            'courriers' => ['count' => (int) ($courriers['nbcourriers'] ?? 0), 'recette' => $rCourriers],
            'bagages' => ['count' => (int) ($bagages['nbbagages'] ?? 0), 'recette' => $rBagages],
            'recetteTotale' => $rBillets + $rCourriers + $rBagages,
        ]);
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function intervalle(string $periode): array
    {
        $fin = new \DateTimeImmutable('now');
        $debut = match ($periode) {
            'jour' => new \DateTimeImmutable('today'),
            'tout' => new \DateTimeImmutable('@0'),
            default => new \DateTimeImmutable('first day of this month 00:00'),
        };

        return [$debut, $fin];
    }

    private function ligneGare(array $rows, int $gareId): array
    {
        foreach ($rows as $row) {
            if ((int) $row['gareid'] === $gareId) {
                return $row;
            }
        }

        return [];
    }
}

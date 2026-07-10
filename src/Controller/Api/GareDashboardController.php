<?php

namespace App\Controller\Api;

use App\Domain\Service\RecetteGareService;
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
 * Tableau de bord de SA gare pour un utilisateur rattaché : recette COMPOSITE + compteurs sur une période
 * (jour / mois / tout). La recette d'une gare = billets guichet (montée) + ventes de SES commerciaux
 * rattachés (billets + bagages, via la gare d'affectation) + réservations payées (gare de provenance) +
 * bagages/courriers déposés, ventilée par CANAL (guichet / commercial / réservation) — source unique
 * RecetteGareService. On y ajoute les INCIDENTS de la gare (billets désistés, bagages/courriers annulés ou
 * perdus). Aucune fuite : ne renvoie que les chiffres de la gare de l'utilisateur courant.
 */
final class GareDashboardController extends AbstractController
{
    #[Route('/api/gares/me/dashboard', name: 'api_gare_me_dashboard', methods: ['GET'])]
    public function dashboard(
        Request $request,
        Security $security,
        RecetteGareService $recetteGareService,
        TicketRepository $ticketRepository,
        BagageRepository $bagageRepository,
        CourrierRepository $courrierRepository
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

        // Recette composite de SA gare (billets guichet + commercial rattaché + réservation + bagages + courriers).
        $gares = $recetteGareService->parGare($debut, $fin, $entId);
        $g = $gares[$gareId] ?? [];

        $billetsRecette = (int) (($g['billetsGuichet'] ?? 0) + ($g['billetsCommercial'] ?? 0));
        $billetsCount = (int) (($g['nbBilletsGuichet'] ?? 0) + ($g['nbBilletsCommercial'] ?? 0));

        // Incidents de la gare (billets désistés, bagages/courriers annulés ou perdus déposés ici).
        $desist = $this->ligneGare($ticketRepository->desistementsParGare($debut, $fin, $entId), $gareId);
        $incBagages = $this->ligneGare($bagageRepository->incidentsParGare($debut, $fin, $entId), $gareId);
        $incCourriers = $this->ligneGare($courrierRepository->incidentsParGare($debut, $fin, $entId), $gareId);

        return $this->json([
            'gare' => ['id' => $gareId, 'libelle' => $gare->getLibelle()],
            'periode' => $periode,
            'billets' => ['count' => $billetsCount, 'recette' => $billetsRecette],
            'courriers' => ['count' => (int) ($g['nbCourriers'] ?? 0), 'recette' => (int) ($g['recetteCourriers'] ?? 0)],
            'bagages' => ['count' => (int) ($g['nbBagages'] ?? 0), 'recette' => (int) ($g['recetteBagages'] ?? 0)],
            'reservations' => ['count' => (int) ($g['nbReservations'] ?? 0), 'recette' => (int) ($g['reservation'] ?? 0)],
            // Ventilation par canal de vente
            'canaux' => [
                'guichet' => (int) ($g['canalGuichet'] ?? 0),
                'commercial' => (int) ($g['canalCommercial'] ?? 0),
                'reservation' => (int) ($g['canalReservation'] ?? 0),
            ],
            'recetteTotale' => (int) ($g['recetteTotale'] ?? 0),
            // Incidents (comptes seuls) : ce que la gare a émis puis annulé / perdu / désisté sur la période
            'incidents' => [
                'ticketsAnnules' => (int) ($desist['nbannules'] ?? 0),
                'ticketsReportes' => (int) ($desist['nbreportes'] ?? 0),
                'bagagesAnnules' => (int) ($incBagages['nbannules'] ?? 0),
                'bagagesPerdus' => (int) ($incBagages['nbperdus'] ?? 0),
                'courriersAnnules' => (int) ($incCourriers['nbannules'] ?? 0),
                'courriersPerdus' => (int) ($incCourriers['nbperdus'] ?? 0),
            ],
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

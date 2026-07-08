<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\TicketRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Statistiques COMMERCIALES (vendeur à bord). La recette billets VALIDE est partitionnée en 3 CANAUX :
 * guichet (recetteParGare) XOR commercial (recetteParCommercial) XOR réservation (recettesReservation).
 * On met ici en valeur la recette captée à bord (hors guichet), le classement des commerciaux et leurs
 * meilleurs trajets. JSON simple.
 */
final class CommercialStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/commercial', name: 'api_stats_commercial', methods: ['GET'])]
    public function index(Request $request, Security $security, TicketRepository $ticketRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Classement des commerciaux (ventes à bord) ──
        $recetteCommerciale = 0;
        $ventes = 0;
        $classement = [];
        foreach ($ticketRepository->recetteParCommercial($debut, $fin, $ent) as $r) {
            $rec = (int) $r['recette'];
            $nb = (int) $r['nbtickets'];
            $recetteCommerciale += $rec;
            $ventes += $nb;
            $nom = trim(($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? '')) ?: 'Commercial #' . $r['commercialid'];
            $classement[] = ['id' => (int) $r['commercialid'], 'nom' => $nom, 'nbtickets' => $nb, 'recette' => $rec];
        }
        usort($classement, fn ($a, $b) => $b['recette'] <=> $a['recette']);

        // ── Autres canaux (guichet gare + réservation) : situent la part commerciale dans le total billets ──
        $recetteGuichet = 0;
        foreach ($ticketRepository->recetteParGare($debut, $fin, $ent) as $r) {
            $recetteGuichet += (int) $r['recette'];
        }
        $recetteReservation = (int) $ticketRepository->recettesReservation($debut, $fin, $ent);
        $recetteBillets = $recetteGuichet + $recetteCommerciale + $recetteReservation;

        // ── Meilleurs trajets du canal commercial ──
        $parTrajet = [];
        foreach ($ticketRepository->recetteCommercialeParTrajet($debut, $fin, $ent) as $r) {
            $parTrajet[] = [
                'trajet' => ($r['de'] ?? '?') . ' → ' . ($r['vers'] ?? '?'),
                'nbtickets' => (int) $r['nbtickets'],
                'recette' => (int) $r['recette'],
            ];
        }

        return new JsonResponse([
            'recetteCommerciale' => $recetteCommerciale,
            'ventes' => $ventes,
            'nbCommerciaux' => count($classement),
            'recetteGuichet' => $recetteGuichet,
            'recetteReservation' => $recetteReservation,
            'recetteBillets' => $recetteBillets,
            'partCommerciale' => $recetteBillets > 0 ? (int) round($recetteCommerciale / $recetteBillets * 100) : 0,
            'panierMoyen' => $ventes > 0 ? (int) round($recetteCommerciale / $ventes) : 0,
            'classement' => $classement,
            'parTrajet' => $parTrajet,
        ]);
    }
}

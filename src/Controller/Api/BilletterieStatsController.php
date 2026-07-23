<?php

namespace App\Controller\Api;

use App\Domain\Trait\PeriodeTrait;
use App\Entity\User;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Compléments billetterie (vue admin) : désistements (reports/annulations), remises (total, par
 * bénéficiaire, par catégorie), matrice Origine-Destination, heures de pointe. JSON simple (pas de DTO).
 */
final class BilletterieStatsController extends AbstractController
{
    use PeriodeTrait;

    #[Route('/api/stats/billetterie/details', name: 'api_stats_billetterie_details', methods: ['GET'])]
    public function details(Request $request, Security $security, TicketRepository $ticketRepository, UserRepository $userRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var User $user */
        $user = $security->getUser();
        $ent = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($request);

        // ── Désistements ──
        $statuts = ['VALIDE' => 0, 'REPORTE' => 0, 'ANNULE' => 0];
        foreach ($ticketRepository->compteParStatut($debut, $fin, $ent) as $r) {
            $statuts[$r['statut']] = (int) $r['nb'];
        }
        $totalBillets = array_sum($statuts);

        // Annulations PAR AGENT qui a annulé (anti « annulation après encaissement ») : nb + montant remboursé
        // + « auto-annulé » (vendu ET annulé par le même agent = signal fort de détournement).
        $rawAnnul = $ticketRepository->annulationsParAgent($debut, $fin, $ent);
        $annulIds = array_filter(array_map(fn ($r) => (int) $r['agentid'], $rawAnnul));
        $nomsAnnul = empty($annulIds) ? [] : $userRepository->findInfosByIds($annulIds);
        $annulationsParAgent = array_map(fn ($r) => [
            'nom' => trim((($nomsAnnul[$r['agentid']]['prenom'] ?? '') . ' ' . ($nomsAnnul[$r['agentid']]['nom'] ?? ''))) ?: '—',
            'nb' => (int) $r['nb'],
            'montant' => (int) $r['montant'],
            'nbself' => (int) $r['nbself'],
        ], $rawAnnul);

        // Suppressions PAR AGENT (anti « vente hors-livre » : billet retiré du livre) : nb + montant VALIDE
        // supprimé + nb supprimés APRÈS le départ (signal fort).
        $rawSuppr = $ticketRepository->suppressionsParAgent($debut, $fin, $ent);
        $supprIds = array_filter(array_map(fn ($r) => (int) $r['agentid'], $rawSuppr));
        $nomsSuppr = empty($supprIds) ? [] : $userRepository->findInfosByIds($supprIds);
        $suppressionsParAgent = array_map(fn ($r) => [
            'nom' => trim((($nomsSuppr[$r['agentid']]['prenom'] ?? '') . ' ' . ($nomsSuppr[$r['agentid']]['nom'] ?? ''))) ?: '—',
            'nb' => (int) $r['nb'],
            'montant' => (int) $r['montant'],
            'nbapresdepart' => (int) $r['nbapresdepart'],
        ], $rawSuppr);

        // Reports IMPUTABLES À LA COMPAGNIE (relogement d'évincés) : à isoler des désistements volontaires.
        // Le taux ne doit refléter que ce que le CLIENT a renoncé — pas ce que la compagnie a repris.
        $reporteEviction = $ticketRepository->compteReportesImputablesCompagnie($debut, $fin, $ent);
        $reporteVolontaire = max(0, $statuts['REPORTE'] - $reporteEviction);
        $totalVolontaire = $reporteVolontaire + $statuts['ANNULE'];

        $desistements = [
            'valide' => $statuts['VALIDE'],
            'reporte' => $statuts['REPORTE'],           // tous les reportés (volontaires + éviction)
            'reporteEviction' => $reporteEviction,       // dont imputables à la compagnie
            'reporteVolontaire' => $reporteVolontaire,   // reports demandés par le client
            'annule' => $statuts['ANNULE'],
            'total' => $totalVolontaire,                 // désistements VOLONTAIRES (hors éviction)
            'taux' => $totalBillets > 0 ? (int) round($totalVolontaire / $totalBillets * 100) : 0,
            'parAgent' => $annulationsParAgent,
            'suppressions' => $suppressionsParAgent,
        ];

        // ── Remises ──
        // TOTAL réel = toutes les remises (le bénéficiaire est FACULTATIF) ; remisesParBeneficiaire (jointure
        // interne) n'en couvre qu'une partie → sinon total à 0 alors que des remises existent.
        $remiseAgg = $ticketRepository->remisesTotales($debut, $fin, $ent);
        $remiseTotal = $remiseAgg['total'];
        $remiseNb = $remiseAgg['nb'];

        // Détail par bénéficiaire / catégorie (uniquement les remises qui EN ont un)
        $parBeneficiaire = [];
        $parCategorie = [];
        $sommeBenef = 0;
        $nbBenef = 0;
        foreach ($ticketRepository->remisesParBeneficiaire($debut, $fin, $ent) as $r) {
            $t = (int) $r['total'];
            $nb = (int) $r['nb'];
            $sommeBenef += $t;
            $nbBenef += $nb;
            $parBeneficiaire[] = ['nom' => $r['nom'] ?? '—', 'categorie' => $r['categorie'], 'total' => $t, 'nb' => $nb];
            $cat = $r['categorie'] ?: 'Non catégorisé';
            $parCategorie[$cat]['categorie'] = $cat;
            $parCategorie[$cat]['total'] = ($parCategorie[$cat]['total'] ?? 0) + $t;
            $parCategorie[$cat]['nb'] = ($parCategorie[$cat]['nb'] ?? 0) + $nb;
        }
        // Part SANS bénéficiaire : le reliquat, pour que le détail réconcilie avec le total réel.
        $sansBenefTotal = $remiseTotal - $sommeBenef;
        $sansBenefNb = $remiseNb - $nbBenef;
        if ($sansBenefTotal > 0 || $sansBenefNb > 0) {
            $parCategorie['__sans__'] = ['categorie' => 'Sans bénéficiaire', 'total' => $sansBenefTotal, 'nb' => $sansBenefNb];
        }
        $parCategorie = array_values($parCategorie);
        usort($parCategorie, fn ($a, $b) => $b['total'] <=> $a['total']);

        // Par AGENT (guichet, createdBy → nom résolu) et par COMMERCIAL (à bord)
        $rawAgents = $ticketRepository->remisesParAgent($debut, $fin, $ent);
        $agentIds = array_filter(array_map(fn ($r) => (int) $r['agentid'], $rawAgents));
        $noms = empty($agentIds) ? [] : $userRepository->findInfosByIds($agentIds);
        $parAgent = array_map(fn ($r) => [
            'nom' => trim((($noms[$r['agentid']]['prenom'] ?? '') . ' ' . ($noms[$r['agentid']]['nom'] ?? ''))) ?: '—',
            'total' => (int) $r['total'],
            'nb' => (int) $r['nb'],
        ], $rawAgents);
        $parCommercial = array_map(fn ($r) => [
            'nom' => trim((($r['prenom'] ?? '') . ' ' . ($r['nom'] ?? ''))) ?: '—',
            'total' => (int) $r['total'],
            'nb' => (int) $r['nb'],
        ], $ticketRepository->remisesParCommercial($debut, $fin, $ent));

        // Taux : part des billets remisés + taux d'effort (remise / chiffre brut avant remise)
        $nbBilletsTotal = $ticketRepository->countTotal($debut, $fin, $ent);
        $recetteBillets = $ticketRepository->recettesTotales($debut, $fin, $ent); // prix net (hors résa, VALIDE)
        $brut = $recetteBillets + $remiseTotal; // tarif plein avant remise
        $tauxBillets = $nbBilletsTotal > 0 ? round($remiseNb / $nbBilletsTotal * 100, 1) : 0;
        $tauxMontant = $brut > 0 ? round($remiseTotal / $brut * 100, 1) : 0;
        $remiseMoyenne = $remiseNb > 0 ? (int) round($remiseTotal / $remiseNb) : 0;

        // ── Matrice O-D ──
        $cells = [];
        $garesMap = [];
        foreach ($ticketRepository->matriceOD($debut, $fin, $ent) as $r) {
            $cells[$r['deId']][$r['versId']] = (int) $r['nb'];
            $garesMap[$r['deId']] = $r['de'];
            $garesMap[$r['versId']] = $r['vers'];
        }
        asort($garesMap); // tri alphabétique des gares
        $gares = [];
        foreach ($garesMap as $id => $lib) {
            $gares[] = ['id' => $id, 'libelle' => $lib];
        }

        // ── Heures de pointe (00–23, trous comblés) ──
        $heuresMap = [];
        foreach ($ticketRepository->billetsParHeure($debut, $fin, $ent) as $r) {
            $heuresMap[(int) $r['heure']] = (int) $r['nb'];
        }
        $heures = [];
        for ($h = 0; $h < 24; $h++) {
            $heures[] = ['heure' => $h, 'nb' => $heuresMap[$h] ?? 0];
        }

        return $this->json([
            'periode' => ['debut' => $debut->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')],
            'desistements' => $desistements,
            'remises' => [
                'total' => $remiseTotal,
                'nb' => $remiseNb,
                'remiseMoyenne' => $remiseMoyenne,
                'tauxBillets' => $tauxBillets,   // % des billets qui ont eu une remise
                'tauxMontant' => $tauxMontant,   // remise / chiffre brut (avant remise)
                'parBeneficiaire' => $parBeneficiaire,
                'parCategorie' => $parCategorie,
                'parAgent' => $parAgent,
                'parCommercial' => $parCommercial,
            ],
            'matriceOD' => ['gares' => $gares, 'cells' => $cells],
            'heures' => $heures,
        ]);
    }
}

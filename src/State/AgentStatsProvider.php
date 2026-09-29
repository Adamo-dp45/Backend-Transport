<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Agent\AgentActionsCritiquesDto;
use App\Entity\Output\Agent\AgentDetailVoyageDto;
use App\Entity\Output\Agent\AgentPerformanceDto;
use App\Entity\Output\Agent\AgentStatistiqueOutput;
use App\Entity\User;
use App\Domain\Service\ConfigRecetteService;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Performance des agents = ce qu'ils ont réellement ENCAISSÉ au guichet (billets + courriers + bagages,
 * ventes commerciales à bord exclues), avec le détail billetterie par voyage. Remplace l'ancien classement
 * « guichet tickets seuls » (il ne reflétait pas toutes leurs ventes). Reprend la logique « par agent » de
 * l'ancienne page Caisse (désormais supprimée).
 */
class AgentStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private TicketRepository $ticketRepository,
        private CourrierRepository $courrierRepository,
        private BagageRepository $bagageRepository,
        private UserRepository $userRepository,
        private ConfigRecetteService $configRecetteService
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        $request = $this->requestStack->getCurrentRequest();
        [$dateDebut, $dateFin] = $this->parsePeriode($request);

        $totalAgents = $this->userRepository->countTotal($identreprise);

        // Billetterie GUICHET par agent (detailParAgentEtVoyage exclut les ventes à bord/commercial), + le détail par voyage
        $agentsMap = [];
        /*
            `created_by` EST NULLABLE SUR TOUTES LES ÉCRITURES, et ce provider n'y était pas préparé :
            une vente sans auteur produisait la clé de tableau `''` (PHP convertit `null` en chaîne
            vide), et `AgentPerformanceDto` déclare `int $id` — TypeError, donc **500 sur tout l'écran**
            pour une seule ligne mal attribuée. Les billets y échappaient par accident, leur requête
            passant par un `INNER JOIN` sur `User` qui écarte ces lignes ; les courriers et les bagages
            lisent `createdBy` directement.

            On ne les CASTE PAS en int : `(int) null` vaut 0, ce qui inventerait un agent n° 0 nommé
            « — ». Une écriture sans auteur n'appartient à personne, elle sort du classement — et son
            montant est compté à part ('recetteNonAttribuee'), pour ne pas disparaître en silence.
        */
        $agentValide = static fn (mixed $id): bool => $id !== null && $id !== '' && (int) $id > 0;
        $recetteNonAttribuee = 0.0;

        foreach ($this->ticketRepository->detailParAgentEtVoyage($dateDebut, $dateFin, $identreprise) as $row) {
            if (!$agentValide($row['agentid'])) {
                $recetteNonAttribuee += (float) $row['recette'];
                continue;
            }
            $id = (int) $row['agentid'];
            $agentsMap[$id]['agentId']        = $id;
            $agentsMap[$id]['nom']            = $row['nom'];
            $agentsMap[$id]['prenom']         = $row['prenom'];
            $agentsMap[$id]['nbtickets']      = ($agentsMap[$id]['nbtickets'] ?? 0) + (int) $row['nbtickets'];
            $agentsMap[$id]['recetteTickets'] = ($agentsMap[$id]['recetteTickets'] ?? 0) + (float) $row['recette'];
            $agentsMap[$id]['detail'][]       = new AgentDetailVoyageDto(
                codevoyage:  $row['codevoyage'],
                provenance:  $row['provenance'],
                destination: $row['destination'],
                nbtickets:   (int) $row['nbtickets'],
                recette:     round((float) $row['recette'], 2),
            );
        }

        // Courriers par agent (createdBy)
        $courriersParAgent = [];
        foreach ($this->courrierRepository->recettesParAgent($dateDebut, $dateFin, $identreprise) as $row) {
            if (!$agentValide($row['agentid'])) {
                $recetteNonAttribuee += (float) $row['montant'];
                continue;
            }
            $id = (int) $row['agentid'];
            $courriersParAgent[$id]['nbcourriers']      = ($courriersParAgent[$id]['nbcourriers'] ?? 0) + (int) $row['nbcourriers'];
            $courriersParAgent[$id]['recetteCourriers'] = ($courriersParAgent[$id]['recetteCourriers'] ?? 0) + (float) $row['montant'];
        }
        foreach ($courriersParAgent as $id => $vals) {
            $agentsMap[$id]['agentId'] = $agentsMap[$id]['agentId'] ?? $id;
            $agentsMap[$id]['nbcourriers'] = $vals['nbcourriers'];
            $agentsMap[$id]['recetteCourriers'] = $vals['recetteCourriers'];
        }

        // Bagages par agent (createdBy)
        $bagagesParAgent = [];
        foreach ($this->bagageRepository->recettesParAgent($dateDebut, $dateFin, $identreprise) as $row) {
            if (!$agentValide($row['agentid'])) {
                $recetteNonAttribuee += (float) $row['montant'];
                continue;
            }
            $id = (int) $row['agentid'];
            $bagagesParAgent[$id]['nbbagages']      = ($bagagesParAgent[$id]['nbbagages'] ?? 0) + (int) $row['nbbagages'];
            $bagagesParAgent[$id]['recetteBagages'] = ($bagagesParAgent[$id]['recetteBagages'] ?? 0) + (float) $row['montant'];
        }
        foreach ($bagagesParAgent as $id => $vals) {
            $agentsMap[$id]['agentId'] = $agentsMap[$id]['agentId'] ?? $id;
            $agentsMap[$id]['nbbagages'] = $vals['nbbagages'];
            $agentsMap[$id]['recetteBagages'] = $vals['recetteBagages'];
        }

        // Résoudre les noms des agents sans tickets (courriers/bagages seulement)
        $idsManquants = array_filter(array_keys($agentsMap), fn ($id) => !isset($agentsMap[$id]['nom']));
        if (!empty($idsManquants)) {
            $usersIndex = $this->userRepository->findInfosByIds($idsManquants);
            foreach ($idsManquants as $id) {
                $agentsMap[$id]['nom']    = $usersIndex[$id]['nom'] ?? '—';
                $agentsMap[$id]['prenom'] = $usersIndex[$id]['prenom'] ?? '—';
            }
        }

        $courriersHorsCa = $this->configRecetteService->courriersHorsCa($identreprise);
        $partCourriers = static fn (array $a): float => $courriersHorsCa ? 0.0 : (float) ($a['recetteCourriers'] ?? 0);

        $performances = array_map(fn ($a) => new AgentPerformanceDto(
            id:               $a['agentId'],
            nom:              $a['nom'],
            prenom:           $a['prenom'],
            nbtickets:        $a['nbtickets'] ?? 0,
            recetteTickets:   round($a['recetteTickets'] ?? 0, 2),
            nbcourriers:      $a['nbcourriers'] ?? 0,
            recetteCourriers: round($a['recetteCourriers'] ?? 0, 2),
            nbbagages:        $a['nbbagages'] ?? 0,
            recetteBagages:   round($a['recetteBagages'] ?? 0, 2),
            /*
                LE TOTAL D'UN AGENT SUIT LA RÈGLE DE LA COMPAGNIE. Il additionnait les courriers sans
                condition : le classement des vendeurs ne s'accordait donc pas avec la recette des gares
                ni avec le bénéfice, chez une compagnie qui met le fret hors chiffre d'affaires. Sa
                recette courrier reste servie à part ('recetteCourriers'), elle ne disparaît pas.
            */
            recetteTotale:    round(($a['recetteTickets'] ?? 0) + $partCourriers($a) + ($a['recetteBagages'] ?? 0), 2),
            detailParVoyage:  $a['detail'] ?? [],
        ), array_values($agentsMap));

        // Tri par recette encaissée décroissante
        usort($performances, fn ($a, $b) => $b->recetteTotale <=> $a->recetteTotale);

        // ── Actions critiques par agent (traçabilité anti-fraude) ──
        // Un agent apparaît keyed par son id, quel que soit son rôle dans l'action (vendeur / annuleur /
        // suppresseur). On agrège toutes les sources puis on ne garde que ceux ayant au moins un incident.
        $crit = [];
        $ligne = static function (int $id) use (&$crit) {
            $crit[$id] ??= [
                'ventesBillets' => 0, 'ventesAnnulees' => 0,
                'annulTickets' => 0, 'annulBagages' => 0, 'annulCourriers' => 0,
                'supprTickets' => 0, 'supprBagages' => 0, 'supprCourriers' => 0,
            ];
        };
        foreach ($this->ticketRepository->tauxAnnulationParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['ventesBillets']  = (int) $r['nbemis'];
            $crit[$id]['ventesAnnulees'] = (int) $r['nbannules'];
        }
        foreach ($this->ticketRepository->annulationsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['annulTickets'] = (int) $r['nb'];
        }
        foreach ($this->ticketRepository->suppressionsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['supprTickets'] = (int) $r['nb'];
        }
        foreach ($this->bagageRepository->annulationsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['annulBagages'] = (int) $r['nb'];
        }
        foreach ($this->bagageRepository->suppressionsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['supprBagages'] = (int) $r['nb'];
        }
        foreach ($this->courrierRepository->annulationsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['annulCourriers'] = (int) $r['nb'];
        }
        foreach ($this->courrierRepository->suppressionsParAgent($dateDebut, $dateFin, $identreprise) as $r) {
            if (!$agentValide($r['agentid'])) { continue; } // pas d'agent n° 0 dans un tableau anti-fraude
            $id = (int) $r['agentid']; $ligne($id);
            $crit[$id]['supprCourriers'] = (int) $r['nb'];
        }

        $nomsCrit = empty($crit) ? [] : $this->userRepository->findInfosByIds(array_keys($crit));
        $actionsCritiques = [];
        foreach ($crit as $id => $r) {
            $incidents = $r['ventesAnnulees'] + $r['annulTickets'] + $r['annulBagages'] + $r['annulCourriers']
                + $r['supprTickets'] + $r['supprBagages'] + $r['supprCourriers'];
            if ($incidents === 0) {
                continue; // agent sans action critique sur la période
            }
            $actionsCritiques[] = new AgentActionsCritiquesDto(
                id:             $id,
                nom:            $nomsCrit[$id]['nom'] ?? '—',
                prenom:         $nomsCrit[$id]['prenom'] ?? '',
                ventesBillets:  $r['ventesBillets'],
                ventesAnnulees: $r['ventesAnnulees'],
                tauxAnnulation: $r['ventesBillets'] > 0 ? round($r['ventesAnnulees'] / $r['ventesBillets'] * 100, 1) : 0.0,
                annulTickets:   $r['annulTickets'],
                annulBagages:   $r['annulBagages'],
                annulCourriers: $r['annulCourriers'],
                supprTickets:   $r['supprTickets'],
                supprBagages:   $r['supprBagages'],
                supprCourriers: $r['supprCourriers'],
            );
        }
        // Tri : taux d'annulation décroissant, puis nombre total de suppressions
        usort($actionsCritiques, fn ($a, $b) =>
            [$b->tauxAnnulation, $b->supprTickets + $b->supprBagages + $b->supprCourriers]
            <=> [$a->tauxAnnulation, $a->supprTickets + $a->supprBagages + $a->supprCourriers]
        );

        return new AgentStatistiqueOutput(
            totalAgents:  $totalAgents,
            agentsActifs: count($performances),
            performances: $performances,
            actionsCritiques: $actionsCritiques,
            recetteNonAttribuee: round($recetteNonAttribuee, 2),
        );
    }
}

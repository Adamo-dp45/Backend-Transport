<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Agent\AgentDetailVoyageDto;
use App\Entity\Output\Agent\AgentPerformanceDto;
use App\Entity\Output\Agent\AgentStatistiqueOutput;
use App\Entity\User;
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
        private UserRepository $userRepository
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
        foreach ($this->ticketRepository->detailParAgentEtVoyage($dateDebut, $dateFin, $identreprise) as $row) {
            $id = $row['agentid'];
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
            $id = $row['agentid'];
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
            $id = $row['agentid'];
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
            recetteTotale:    round(($a['recetteTickets'] ?? 0) + ($a['recetteCourriers'] ?? 0) + ($a['recetteBagages'] ?? 0), 2),
            detailParVoyage:  $a['detail'] ?? [],
        ), array_values($agentsMap));

        // Tri par recette encaissée décroissante
        usort($performances, fn ($a, $b) => $b->recetteTotale <=> $a->recetteTotale);

        return new AgentStatistiqueOutput(
            totalAgents:  $totalAgents,
            agentsActifs: count($performances),
            performances: $performances,
        );
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Agent\AgentPerformanceDto;
use App\Entity\Output\Agent\AgentStatistiqueOutput;
use App\Entity\User;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AgentStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private TicketRepository $ticketRepository,
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

        $totalAgents  = $this->userRepository->countTotal($identreprise);
        $rawAgentsActifs = $this->ticketRepository->performancesParAgent($dateDebut, $dateFin, $identreprise);

        // Index des agents actifs pour lookup
        $actifsIndex = [];
        foreach ($rawAgentsActifs as $row) {
            $actifsIndex[$row['id']] = $row;
        }

        // Tous les agents avec leurs stats — actifs depuis l'index, inactifs à zéro
        $tousAgents = $this->userRepository->findByEntreprise($identreprise);

        $performances = array_map(function($user) use ($actifsIndex) {
            $stats = $actifsIndex[$user['id']] ?? null;
            return new AgentPerformanceDto(
                id:        $user['id'],
                nom:       $user['nom'],
                prenom:    $user['prenom'],
                nbtickets: $stats ? (int)$stats['nbtickets'] : 0,
                recette:   $stats ? round((float)$stats['recette'], 2) : 0.0,
                actif:     $stats !== null,
            );
        }, $tousAgents);

        // Tri par recette décroissante
        usort($performances, fn($a, $b) => $b->recette <=> $a->recette);

        return new AgentStatistiqueOutput(
            totalAgents:  $totalAgents,
            agentsActifs: count($actifsIndex),
            performances: $performances,
        );
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\FideliteService;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Fidelite\FideliteStatistiqueOutput;
use App\Entity\Output\Fidelite\TopMembreDto;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistiques du programme de fidélité, calculées (comme l'état) à partir de l'historique des billets.
 */
class FideliteStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private FideliteService $fideliteService,
        private ClientRepository $clientRepository,
        private TicketRepository $ticketRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        [$debut, $fin] = $this->parsePeriode($this->requestStack->getCurrentRequest());

        $programme = $this->fideliteService->getProgramme($identreprise);
        $seuil = max(1, $programme->getSeuil());

        $totalClients = $this->clientRepository->countActifs($identreprise);
        $totalMembres = $this->clientRepository->countMembres($identreprise);
        $adhesions    = $this->clientRepository->countAdhesions($debut, $fin, $identreprise);
        $recompenses  = $this->ticketRepository->statsRecompensesPeriode($debut, $fin, $identreprise);

        // Agrégats par membre (1 requête chacun) → on calcule tampons / récompenses en mémoire
        $voyages = [];
        foreach ($this->ticketRepository->voyagesParMembre($identreprise) as $r) {
            $voyages[(int) $r['clientid']] = (int) $r['nb'];
        }
        $utilisees = [];
        foreach ($this->ticketRepository->recompensesParMembre($identreprise) as $r) {
            $utilisees[(int) $r['clientid']] = (int) $r['nb'];
        }

        $recompensesDisponibles = 0;
        $top = [];
        foreach ($this->clientRepository->findMembres($identreprise) as $m) {
            $id = (int) $m['id'];
            $v = $voyages[$id] ?? 0;
            $u = $utilisees[$id] ?? 0;
            $stamps = max(0, $v - $u * $seuil);
            $dispo = $programme->isActif() && intdiv($stamps, $seuil) >= 1;
            if ($dispo) {
                $recompensesDisponibles++;
            }
            $top[] = new TopMembreDto(
                id: $id,
                nom: $m['nom'],
                contact: $m['contact'],
                voyages: $v,
                recompensesUtilisees: $u,
                progression: $stamps % $seuil,
                recompenseDisponible: $dispo
            );
        }
        // Top membres par voyages cumulés
        usort($top, fn(TopMembreDto $a, TopMembreDto $b) => $b->voyages <=> $a->voyages);
        $top = array_slice($top, 0, 10);

        return new FideliteStatistiqueOutput(
            totalClients: $totalClients,
            totalMembres: $totalMembres,
            tauxAdhesion: $totalClients > 0 ? round($totalMembres / $totalClients * 100, 1) : 0.0,
            adhesionsPeriode: $adhesions,
            recompensesUtilisees: $recompenses['nb'],
            valeurRecompenses: $recompenses['valeur'],
            recompensesDisponibles: $recompensesDisponibles,
            seuil: $programme->getSeuil(),
            recompensePourcentage: $programme->getRecompensePourcentage(),
            programmeActif: $programme->isActif(),
            topMembres: $top
        );
    }
}

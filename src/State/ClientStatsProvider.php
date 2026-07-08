<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Client\ClientStatistiqueOutput;
use App\Entity\Output\Client\TopClientDto;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\TicketRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Statistiques de la base CLIENTS (toute l'entreprise, membres ou non) : volume, nouveaux/actifs,
 * panier moyen et classements (clients les plus fidèles par voyages, plus gros acheteurs par dépense).
 */
class ClientStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
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

        $totalClients = $this->clientRepository->countActifs($identreprise);
        $membres      = $this->clientRepository->countMembres($identreprise);
        $nouveaux     = $this->clientRepository->countNouveaux($debut, $fin, $identreprise);
        $actifs       = $this->ticketRepository->statsClientsActifs($debut, $fin, $identreprise);

        $panierMoyen = $actifs['clients'] > 0 ? (int) round($actifs['recette'] / $actifs['clients']) : 0;

        $map = fn (array $r) => new TopClientDto(
            id: (int) $r['id'],
            nom: $r['nom'],
            contact: $r['contact'],
            nbBillets: (int) $r['nb'],
            depense: (int) $r['depense'],
            membre: (bool) $r['membre']
        );

        return new ClientStatistiqueOutput(
            totalClients: $totalClients,
            membres: $membres,
            nouveauxClients: $nouveaux,
            clientsActifs: $actifs['clients'],
            panierMoyen: $panierMoyen,
            topParBillets: array_map($map, $this->ticketRepository->topClients($debut, $fin, $identreprise, 'nb', 10)),
            topParDepense: array_map($map, $this->ticketRepository->topClients($debut, $fin, $identreprise, 'depense', 10))
        );
    }
}

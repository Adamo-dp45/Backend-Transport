<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Client\ClientStatistiqueOutput;
use App\Entity\Output\Client\TopClientDto;
use App\Entity\User;
use App\Repository\BagageRepository;
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
        private TicketRepository $ticketRepository,
        private BagageRepository $bagageRepository
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

        // Dépense BAGAGES par client (le bagage est de l'argent que le client a payé → compté dans sa dépense)
        $bagParClient = [];
        $totalBagages = 0;
        foreach ($this->bagageRepository->depenseParClient($debut, $fin, $identreprise) as $r) {
            $bagParClient[(int) $r['clientid']] = (int) $r['montant'];
            $totalBagages += (int) $r['montant'];
        }

        // Panier moyen = (recette billets + bagages) / clients actifs
        $panierMoyen = $actifs['clients'] > 0 ? (int) round(($actifs['recette'] + $totalBagages) / $actifs['clients']) : 0;

        $map = fn (array $r) => new TopClientDto(
            id: (int) $r['id'],
            nom: $r['nom'],
            contact: $r['contact'],
            nbBillets: (int) $r['nb'],
            depense: (int) $r['depense'] + ($bagParClient[(int) $r['id']] ?? 0), // billets + bagages
            depenseBagages: $bagParClient[(int) $r['id']] ?? 0,
            membre: (bool) $r['membre']
        );

        // Top par dépense : on inclut les bagages puis on re-classe (buffer 25 pour ne pas rater un gros
        // acheteur de bagages hors du top billets), coupé à 10.
        $topDepense = array_map($map, $this->ticketRepository->topClients($debut, $fin, $identreprise, 'depense', 25));
        usort($topDepense, fn ($a, $b) => $b->depense <=> $a->depense);
        $topDepense = array_slice($topDepense, 0, 10);

        return new ClientStatistiqueOutput(
            totalClients: $totalClients,
            membres: $membres,
            nouveauxClients: $nouveaux,
            clientsActifs: $actifs['clients'],
            panierMoyen: $panierMoyen,
            topParBillets: array_map($map, $this->ticketRepository->topClients($debut, $fin, $identreprise, 'nb', 10)),
            topParDepense: $topDepense
        );
    }
}

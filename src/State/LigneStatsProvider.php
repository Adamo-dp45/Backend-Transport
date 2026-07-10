<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Ligne\LignePerformanceDto;
use App\Entity\Output\Ligne\LigneStatistiqueOutput;
use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\LigneRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class LigneStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private LigneRepository $ligneRepository,
        private ReservationRepository $reservationRepository,
        private CourrierRepository $courrierRepository,
        private BagageRepository $bagageRepository
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

        // Réservations payées / courriers / bagages par ligne — à additionner aux billets directs.
        $resaParLigne = [];
        foreach ($this->reservationRepository->recettesPayeesParLigne($dateDebut, $dateFin, $identreprise) as $r) {
            $resaParLigne[(int) $r['ligneid']] = ['nb' => (int) $r['nbreservations'], 'recette' => (float) $r['recette']];
        }
        $courriersParLigne = [];
        foreach ($this->courrierRepository->recetteParLigne($dateDebut, $dateFin, $identreprise) as $r) {
            $courriersParLigne[(int) $r['ligneid']] = ['nb' => (int) $r['nbcourriers'], 'recette' => (float) $r['recette']];
        }
        $bagagesParLigne = [];
        foreach ($this->bagageRepository->recetteParLigne($dateDebut, $dateFin, $identreprise) as $r) {
            $bagagesParLigne[(int) $r['ligneid']] = ['nb' => (int) $r['nbbagages'], 'recette' => (float) $r['recette']];
        }

        $performances = array_map(
            function ($row) use ($resaParLigne, $courriersParLigne, $bagagesParLigne) {
                $id = (int) $row['id'];
                $recetteBillets = round((float) $row['recette'] + ($resaParLigne[$id]['recette'] ?? 0), 2); // billets directs + réservations
                $recetteCourriers = round($courriersParLigne[$id]['recette'] ?? 0, 2);
                $recetteBagages = round($bagagesParLigne[$id]['recette'] ?? 0, 2);

                return new LignePerformanceDto(
                    id: $id,
                    libelle: $row['libelle'],
                    codeligne: $row['codeligne'],
                    nbvoyages: (int) $row['nbvoyages'],
                    nbtickets: (int) $row['nbtickets'] + ($resaParLigne[$id]['nb'] ?? 0), // billets directs (VALIDE, hors désistés) + réservations payées
                    recetteBillets: $recetteBillets,
                    nbcourriers: $courriersParLigne[$id]['nb'] ?? 0,
                    recetteCourriers: $recetteCourriers,
                    nbbagages: $bagagesParLigne[$id]['nb'] ?? 0,
                    recetteBagages: $recetteBagages,
                    recette: round($recetteBillets + $recetteCourriers + $recetteBagages, 2), // grand total
                );
            },
            $this->ligneRepository->findAllAvecStats($dateDebut, $dateFin, $identreprise)
        );

        // Re-tri par recette TOTALE décroissante (le SQL trie sur la recette billets seule, avant les ajouts)
        usort($performances, fn ($a, $b) => $b->recette <=> $a->recette);

        return new LigneStatistiqueOutput(
            totalLignes: $this->ligneRepository->countTotal($identreprise),
            performances: $performances,
        );
    }
}

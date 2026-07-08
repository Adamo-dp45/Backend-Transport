<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Bagage\BagageStatistiqueOutput;
use App\Entity\Output\Bagage\RecetteBagageParJourDto;
use App\Entity\User;
use App\Repository\BagageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class BagageStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
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

        $statuts = $this->bagageRepository->countParStatut($dateDebut, $dateFin, $identreprise);
        $recetteTotale = $this->bagageRepository->recettesTotales($dateDebut, $dateFin, $identreprise);
        $poidsTotal = $this->bagageRepository->poidsTotal($dateDebut, $dateFin, $identreprise);

        $recettesParJour = array_map(
            fn($row) => new RecetteBagageParJourDto(
                label: $row['label'],
                montant: round((float)$row['montant'], 2),
                nbbagages: (int)$row['nbbagages'],
                poids: (int)$row['poids']
            ),
            $this->bagageRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise)
        );

        return new BagageStatistiqueOutput(
            totalBagages: array_sum($statuts),
            enregistres: $statuts['ENREGISTRE'] ?? 0,
            embarques: $statuts['EMBARQUE'] ?? 0,
            livres: $statuts['LIVRE'] ?? 0,
            perdus: $statuts['PERDU'] ?? 0,
            annules: $statuts['ANNULE'] ?? 0, // bagages annulés via l'annulation du billet du client
            recetteTotale: $recetteTotale,
            poidsTotal: $poidsTotal,
            recettesParJour: $recettesParJour
        );
    }
}

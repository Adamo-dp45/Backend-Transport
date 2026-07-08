<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Courrier\CourrierStatistiqueOutput;
use App\Entity\Output\Courrier\RecetteCourrierParJourDto;
use App\Entity\Output\Courrier\RecetteCourrierParTrajetDto;
use App\Entity\User;
use App\Repository\CourrierRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CourrierStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private CourrierRepository $courrierRepository
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

        $statuts = $this->courrierRepository->countParStatut($dateDebut, $dateFin, $identreprise);
        $recetteTotale = $this->courrierRepository->recettesTotales($dateDebut, $dateFin, $identreprise);

        $recettesParJour = array_map(
            fn($row) => new RecetteCourrierParJourDto(
                label: $row['label'],
                montant: round((float)$row['montant'], 2),
                nbcourriers: (int)$row['nbcourriers'],
            ),
            $this->courrierRepository->recettesParJourDetail($dateDebut, $dateFin, $identreprise)
        );

        $recettesParTrajet = array_map(
            fn($row) => new RecetteCourrierParTrajetDto(
                trajet: $row['trajet'],
                montant: round((float)$row['montant'], 2),
                nbcourriers: (int)$row['nbcourriers'],
            ),
            $this->courrierRepository->recettesParTrajetDetail($dateDebut, $dateFin, $identreprise)
        );

        $totalCourriers = array_sum($statuts);

        return new CourrierStatistiqueOutput(
            totalCourriers: $totalCourriers,
            enAttente: $statuts['EN_ATTENTE'] ?? 0,
            enTransit: $statuts['EN_TRANSIT'] ?? 0,
            receptionnes: $statuts['RECEPTIONNE'] ?? 0,
            livres: $statuts['LIVRE'] ?? 0,
            perdus: $statuts['PERDU'] ?? 0,
            annules: $statuts['ANNULE'] ?? 0,
            recetteTotale: $recetteTotale,
            recettesParJour: $recettesParJour,
            recettesParTrajet: $recettesParTrajet
        );
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Ligne\LignePerformanceDto;
use App\Entity\Output\Ligne\LigneStatistiqueOutput;
use App\Entity\User;
use App\Repository\LigneRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class LigneStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private LigneRepository $ligneRepository
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

        $performances = array_map(
            fn($row) => new LignePerformanceDto(
                id: $row['id'],
                libelle: $row['libelle'],
                codeligne: $row['codeligne'],
                nbvoyages: (int) $row['nbvoyages'],
                nbtickets: (int) $row['nbtickets'],
                recette: round((float) $row['recette'], 2),
            ),
            $this->ligneRepository->findAllAvecStats($dateDebut, $dateFin, $identreprise)
        );

        return new LigneStatistiqueOutput(
            totalLignes: $this->ligneRepository->countTotal($identreprise),
            performances: $performances,
        );
    }
}

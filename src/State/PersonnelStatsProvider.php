<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\Personnel\PersonnelPerformanceDto;
use App\Entity\Output\Personnel\PersonnelStatistiqueOutput;
use App\Entity\User;
use App\Repository\DetailpersonnelRepository;
use App\Repository\PersonnelRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class PersonnelStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private PersonnelRepository $personnelRepository,
        private DetailpersonnelRepository $detailpersonnelRepository
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

        $tousPersonnels = $this->personnelRepository->findAllAvecType($identreprise);
        $affectations = $this->detailpersonnelRepository->affectationsParPersonnel($dateDebut, $dateFin, $identreprise);
        $personnelsActifs = 0;

        $performances = array_map(function($p) use ($affectations, &$personnelsActifs) {
            $stats = $affectations[$p['id']] ?? null;
            $nbvoyages = $stats['nbvoyages'] ?? 0;
            $nbdepannages = $stats['nbdepannages'] ?? 0;
            $actif = $stats !== null;

            if($actif) {
                $personnelsActifs++;
            }

            return new PersonnelPerformanceDto(
                id: $p['id'],
                nom: $p['nom'],
                prenom: $p['prenom'],
                type: $p['type'],
                nbvoyages: $nbvoyages,
                nbdepannages: $nbdepannages,
                actif: $actif
            );
        }, $tousPersonnels);

        // Tri par total affectations décroissant
        usort($performances, fn($a, $b) =>
            ($b->nbvoyages + $b->nbdepannages) <=> ($a->nbvoyages + $a->nbdepannages)
        );

        return new PersonnelStatistiqueOutput(
            totalPersonnels:  $this->personnelRepository->countTotal($identreprise),
            personnelsActifs: $personnelsActifs,
            performances:     $performances,
        );
    }
}

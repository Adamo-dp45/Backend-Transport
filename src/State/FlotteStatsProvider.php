<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Output\Flotte\FlotteStatistiqueOutput;
use App\Entity\Output\Flotte\VehiculeDepannageDto;
use App\Entity\Output\Flotte\VehiculeEtatDto;
use App\Entity\Output\Flotte\VehiculeMaintenanceDto;
use App\Entity\User;
use App\Repository\CarRepository;
use App\Repository\DepannageRepository;
use Symfony\Bundle\SecurityBundle\Security;

class FlotteStatsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private DepannageRepository $depannageRepository,
        private CarRepository $carRepository
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

        $vehiculesParEtat = array_map(
            fn($row) => new VehiculeEtatDto(
                etat:  $row['etat'],
                total: (int)$row['total'],
            ),
            $this->carRepository->countParEtat($identreprise)
        );

        $vehiculesParDepannage = array_map(
            fn($row) => new VehiculeDepannageDto(
                matricule: $row['matricule'],
                nbrdepannages: (int)$row['nbrdepannages'],
            ),
            $this->depannageRepository->countParVehicule($identreprise)
        );

        $coutMaintenanceParVehicule = array_map(
            fn($row) => new VehiculeMaintenanceDto(
                matricule: $row['matricule'],
                couttotal: round((float)$row['couttotal'], 2),
            ),
            $this->depannageRepository->coutParVehicule($identreprise)
        );

        return new FlotteStatistiqueOutput(
            totalVehicules: $this->carRepository->countTotal($identreprise),
            vehiculesParEtat: $vehiculesParEtat,
            vehiculesParDepannage: $vehiculesParDepannage,
            coutMaintenanceParVehicule: $coutMaintenanceParVehicule
        );
    }
}

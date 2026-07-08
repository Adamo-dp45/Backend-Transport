<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Trait\PeriodeTrait;
use App\Entity\Output\FlotteActivity\FlotteActiviteOutput;
use App\Entity\Output\FlotteActivity\VehiculeActiviteDto;
use App\Entity\User;
use App\Repository\CarRepository;
use App\Repository\DepannageRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class FlotteActiviteStatsProvider implements ProviderInterface
{
    use PeriodeTrait;

    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private CarRepository $carRepository,
        private VoyageRepository $voyageRepository,
        private DepannageRepository $depannageRepository
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

        $tousCars = $this->carRepository->findAllAvecEtat($identreprise);
        $voyagesParCar = $this->voyageRepository->countParCar($dateDebut, $dateFin, $identreprise);
        $depannagesParCar = $this->depannageRepository->countParCar($dateDebut, $dateFin, $identreprise);

        // Index par carid
        $voyagesIndex = [];
        foreach ($voyagesParCar as $row) {
            $voyagesIndex[(int)$row['carid']] = (int)$row['nbvoyages'];
        }

        $depannagesIndex = [];
        foreach ($depannagesParCar as $row) {
            $depannagesIndex[(int)$row['carid']] = (int)$row['nbdepannages'];
        }

        $vehiculesActifs = 0;

        $activiteParVehicule = array_map(function($car) use ($voyagesIndex, $depannagesIndex, &$vehiculesActifs) {
            $nbvoyages    = $voyagesIndex[$car['id']] ?? 0;
            $nbdepannages = $depannagesIndex[$car['id']] ?? 0;
            $actif        = $nbvoyages > 0 || $nbdepannages > 0;

            if ($actif) $vehiculesActifs++;

            return new VehiculeActiviteDto(
                id:           $car['id'],
                matricule:    $car['matricule'],
                etat:         $car['etat'],
                nbvoyages:    $nbvoyages,
                nbdepannages: $nbdepannages,
                actif:        $actif,
            );
        }, $tousCars);

        // Tri par nbvoyages décroissant
        usort($activiteParVehicule, fn($a, $b) => $b->nbvoyages <=> $a->nbvoyages);

        return new FlotteActiviteOutput(
            totalVehicules: $this->carRepository->countTotal($identreprise),
            vehiculesEnVoyage: $this->carRepository->countEnVoyage($identreprise),
            activiteParVehicule: $activiteParVehicule,
        );
    }
}

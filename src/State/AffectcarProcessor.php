<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\CarStatutService;
use App\Entity\Dto\AffectcarInput;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AffectcarProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private CarStatutService $carStatutService,
        private VoyageGuard $guard
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AffectcarInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);

        if(!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        // Exploitation (affecter/CHANGER le car) : ouverte à toute gare de la route effective sauf terminus
        // → permet à la gare où le car est tombé en panne de le remplacer.
        $this->guard->assertPeutGerer($user, $voyage);

        if($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : impossible d\'affecter un véhicule');
        }

        $car = $data->car;
        $ancienCar = $voyage->getCar();

        // Même car → rien à faire (idempotent)
        if($ancienCar && $ancienCar->getId() === $car->getId()) {
            return $this->processor->process($voyage, $operation, $uriVariables, $context);
        }

        // Le nouveau car ne doit pas déjà rouler sur un autre voyage en cours
        $voyageActif = $this->em->getRepository(Voyage::class)->findOneBy([
            'car' => $car,
            'identreprise' => $entrepriseId,
            'datearriveereelle' => null,
            'deletedAt' => null
        ]);
        if($voyageActif && $voyageActif->getId() !== $voyage->getId()) {
            throw new BadRequestHttpException(
                sprintf(
                    'Ce véhicule est déjà affecté au voyage "%s" (%s → %s) qui est en cours. Clôturez ce voyage avant de l\'affecter à un autre.',
                    $voyageActif->getCodevoyage(),
                    $voyageActif->getProvenance(),
                    $voyageActif->getDestination()
                )
            );
        }

        // CHANGEMENT de car (ex. panne) : les places déjà vendues ne doivent pas dépasser le nouveau car
        if($ancienCar && $voyage->getTicketsCount() > $car->getNbrsiege()) {
            throw new BadRequestHttpException('Impossible de changer de car : les places déjà occupées dépassent la capacité du nouveau véhicule');
        }

        $this->carStatutService->verifierDisponibiliteVoyage($car);
        $this->carStatutService->mettreEnVoyage($car);
        $voyage->setCar($car);
        $voyage->setPlacesTotal($car->getNbrsiege());

        // Remplacement : libérer l'ancien car et réaffecter les sièges des billets (même numéro) au nouveau.
        if($ancienCar) {
            $this->carStatutService->mettreDisponible($ancienCar);
            $tickets = $this->em->getRepository(Ticket::class)->findBy(['voyage' => $voyage, 'deletedAt' => null]);
            foreach($tickets as $ticket) {
                if(!$ticket->getSiege()) {
                    continue;
                }
                $nouveauSiege = $this->em->getRepository(Siege::class)->findOneBy([
                    'car' => $car,
                    'numero' => $ticket->getSiege()->getNumero(),
                ]);
                if($nouveauSiege) {
                    $ticket->setSiege($nouveauSiege);
                }
            }
        }

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CarStatutService;
use App\Entity\Car;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class VoyageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private CarStatutService $carStatutService,
        private VoyageGuard $guard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Voyage $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $ligne = $data->getLigne();
            if(!$ligne) {
                throw new BadRequestHttpException('La ligne est obligatoire pour créer un voyage');
            }

            // Provenance RÉELLE du voyage = gare de l'agent (DÉPART PARTIEL si intermédiaire) ; admin/central
            // → origine de la ligne (départ normal). assertPeutCreerDepart valide + renvoie la provenance.
            $gareProvenance = $this->guard->assertPeutCreerDepart($user, $ligne);
            $data
                ->setGareprovenance($gareProvenance)
                ->setGarecourante($gareProvenance) // position initiale du car = sa provenance (pas l'origine de la ligne)
                ->setProvenance($gareProvenance->getLibelle())
                ->setDestination($ligne->getGareterminus()->getLibelle());

            // Unicité : pas 2 voyages sur la même ligne AU MÊME POINT DE DÉPART au même moment
            // (un départ normal ET un départ partiel intermédiaire peuvent coexister sur la même ligne/date)
            $existant = $this->em->getRepository(Voyage::class)->findOneBy([
                'ligne' => $ligne,
                'gareprovenance' => $gareProvenance,
                'datedepartprevue' => $data->getDatedepartprevue(),
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);
            if($existant) {
                throw new ConflictHttpException('Un voyage existe déjà pour cette ligne à cette date');
            }

            $data
                ->setIdentreprise($entrepriseId)
                ->setCreatedBy($user->getId())
            ;

            $code = $this->em->getRepository(Voyage::class)->count([
                'ligne' => $ligne,
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]) + 1;
            $data
                ->setCodevoyage($ligne->getCodeligne() . '-V' . $code); /*
                - On peut avoir un problème de concurrence '2 créations en même temps' donc à améliorer
            */
            if($data->getCar()) {
                $this->carStatutService->verifierDisponibiliteVoyage($data->getCar()); /*
                    - On vérifie la disponibilité du car avant d'affecter
                */
                $this->getCar($data);
                $this->carStatutService->mettreEnVoyage($data->getCar());
            } else {
                $data->setPlacesTotal(0);
            }
        }

        if($operation instanceof Patch) {
            $original = $this->em->getUnitOfWork()->getOriginalEntityData($data); /*
                - Pour récupérer l'état original de l'objet depuis la base de données avant les modifications sinon '$data->getDatearriveereelle()' nous donne l'état avant modification
            */
            if(!empty($original['datearriveereelle'])) {
                throw new BadRequestHttpException('Ce voyage est déjà clôturé et ne peut plus être modifié');
            }

            if($data->getProvenance() === $data->getDestination()) {
                throw new BadRequestHttpException('La provenance et la destination ne peuvent pas être identiques');
            }

            if($data->getDatearriveereelle() && $data->getDatearriveereelle() <= $data->getDatedepartprevue()) {
                throw new BadRequestHttpException('La date de fin doit être supérieure à la date de départ');
            }

            $data->setUpdatedBy($user->getId());
            /*
                if($data->getCar()) {
                    $this->getCar($data);
                }
            */
            if($data->getDatearriveereelle() !== null) {
                // La CLÔTURE a désormais sa route dédiée /voyages/{id}/cloturer (CloturerVoyageProcessor) :
                // datearriveereelle n'est plus dans le groupe d'écriture de ce Patch, ce cas est donc
                // inatteignable en pratique — garde-fou défensif si quelqu'un force le champ.
                throw new BadRequestHttpException('La clôture se fait via la route dédiée /voyages/{id}/cloturer');
            } else {
                // Modification (hors clôture) = planification : réservée à la gare d'ORIGINE
                $this->guard->assertPeutPlanifier($user, $data);

                $oldCarId = $original['car_id'] ?? null; /*
                    - On récupère l'ancine car en cas de changement de car
                */
                $newCar = $data->getCar();
                if($newCar) {
                    $newCarId = $newCar->getId();
                    if($oldCarId !== $newCarId) {
                        $oldMat = null;
                        if($oldCarId) {
                            $oldCar = $this->em->getRepository(Car::class)->find($oldCarId);
                            if($oldCar) {
                                $oldMat = $oldCar->getMatricule();
                                $this->carStatutService->mettreDisponible($oldCar); /*
                                    - On libère l'ancien car
                                */
                            }
                        }
                        $this->carStatutService->verifierDisponibiliteVoyage($newCar); /*
                            - On vérifie la disponibilité du nouveau car avant d'affecter
                        */
                        $this->carStatutService->mettreEnVoyage($newCar);

                        /* On.. vu que les les sièges sont liés au car et pas au voyage, quand on change le car les anciens tickets pointait vers des sièges de l'ancien car donc on a réaffecter les sièges automatiquement
                         */
                        $tickets = $this->em->getRepository(Ticket::class)->findBy([
                            'voyage' => $data,
                            'deletedAt' => null
                        ]); /*
                            - On récupère les tickets actifs du voyage
                        */
                        foreach($tickets as $ticket) {
                            $ancienNumero = $ticket->getSiege()->getNumero();
                            $nouveauSiege = $this->em->getRepository(Siege::class)->findOneBy([
                                'car' => $data->getCar(),
                                'numero' => $ancienNumero
                            ]); /*
                                - On cherche le siège de même numéro dans le nouveau car
                            */
                            if($nouveauSiege) {
                                $ticket->setSiege($nouveauSiege);
                            } /*
                                - Si le siège n'existe pas dans le nouveau car ou capacité différente.. déjà gérer
                            */
                        }

                        // Journal : on trace le changement de car (l'ancien matricule serait perdu sinon)
                        $this->activiteLogger->voyage(
                            ActiviteLogger::VOYAGE_CAR,
                            $oldMat !== null
                                ? sprintf('Car changé : %s → %s', $oldMat, $newCar->getMatricule())
                                : sprintf('Car affecté : %s', $newCar->getMatricule()),
                            $data->getId()
                        );
                    }
                    $this->getCar($data);
                } /*
                    - Si on peut retirer le car du voyage
                    elseif($oldCarId && $newCar === null) {
                        $oldCar = $this->em->getRepository(Car::class)->find($oldCarId);
                        if($oldCar) {
                            $this->carStatutService->mettreDisponible($oldCar);
                        }
                    }
                */
            }
        }
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function getCar(Voyage $data)
    {
        if($data->getCar()) { /*
            - On vérifie si le car est déjà utilisé sur un autre voyage au même moment
        */
            $existingCarForVoyage = $this->em->getRepository(Voyage::class)
                ->createQueryBuilder('v')
                ->where('v.car = :car')
                ->andWhere('v.id != :currentId OR :currentId IS NULL')
                ->andWhere('v.datearriveereelle IS NULL')
                ->andWhere('v.deletedAt IS NULL')
                ->setParameter('car', $data->getCar())
                ->setParameter('currentId', $data->getId())
                ->getQuery()
                ->getOneOrNullResult()
            ;
            if($existingCarForVoyage) {
                throw new BadRequestHttpException(
                    'Ce véhicule est déjà utilisé sur un voyage en cours, clôturez-le avant de l\'affecter à un nouveau voyage'
                );
            }

            $places = $data->getCar()->getNbrSiege();
            if($data->getTicketsCount() > $places) { /*
                - On vérifie que les billets déjà vendus ne dépassent pas la capacité du nouveau car en cas de 'patch'
            */
                throw new BadRequestHttpException('Impossible de changer de Car : les places déjà occupées dépassent la capacité du nouveau véhicule');
            }
            $data->setPlacesTotal($places);
        }
    }
}

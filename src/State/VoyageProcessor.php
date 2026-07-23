<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\CarStatutService;
use App\Domain\Service\ReaffectationSiegeService;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Car;
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
        private ActiviteLogger $activiteLogger,
        private ReservationEcheanceService $reservationEcheance,
        private CapaciteService $capaciteService,
        private ReaffectationSiegeService $reaffectationSiege
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
                ->setDestination($ligne->getGareterminus()->getLibelle())
            ;
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

                /*
                    DÉPART DÉCALÉ → les échéances des réservations doivent suivre. Elles sont calées sur
                    la date de départ : sans recalcul, un report aurait déclaré no-show des clients ayant
                    payé (le car n'était pas parti), et une avance aurait laissé des réservations
                    « valides » après le départ réel. Cf. ReservationEcheanceService.
                */
                /*
                    CAPACITÉ PRÉVISIONNELLE : tant qu'aucun car n'est affecté, c'est elle qui borne les
                    réservations (cf. CapaciteService::capaciteEffective). L'abaisser sous ce qui est
                    déjà engagé créerait des clients payés sans place — même faute que d'affecter un car
                    trop petit, donc même garde. Sans objet dès qu'un car est affecté : sa capacité prime.
                */
                $nouvellesPlaces = $data->getPlacesprevues();
                if($data->getCar() === null && $nouvellesPlaces !== null && $data->getId() !== null) {
                    $occupation = $this->capaciteService->occupationMaximale($data, $entrepriseId);
                    if($occupation > $nouvellesPlaces) {
                        throw new BadRequestHttpException(sprintf(
                            'Impossible de ramener la capacité prévisionnelle à %d place(s) : %d sont déjà engagées sur ce voyage (billets émis et réservations).',
                            $nouvellesPlaces,
                            $occupation
                        ));
                    }
                }

                $ancienDepart = $original['datedepartprevue'] ?? null;
                $nouveauDepart = $data->getDatedepartprevue();
                if ($nouveauDepart !== null && $ancienDepart instanceof \DateTimeInterface
                    && $ancienDepart->getTimestamp() !== $nouveauDepart->getTimestamp()
                ) {
                    // flush: false → les réservations sont écrites par le flush FINAL, avec le voyage.
                    // Flusher ici enregistrerait la nouvelle date avant les contrôles qui suivent
                    // (disponibilité du car…) : un refus laisserait la base à moitié modifiée.
                    // L'ancienne date sert à détecter une AVANCE : les clients déjà payés qui ne
                    // pourront pas suivre ne seront pas pénalisés (le changement vient de nous).
                    $this->reservationEcheance->replanifierPourVoyage($data, $ancienDepart, flush: false);
                }

                $oldCarId = $original['car_id'] ?? null; /*
                    - On récupère l'ancine car en cas de changement de car
                */
                $newCar = $data->getCar();
                if($newCar) {
                    $newCarId = $newCar->getId();
                    if($oldCarId !== $newCarId) {
                        $this->carStatutService->verifierDisponibiliteVoyage($newCar); /*
                            - On vérifie la disponibilité du nouveau car avant d'affecter
                        */

                        /*
                            Le véhicule doit pouvoir HONORER ce qui est déjà engagé : billets émis ET
                            réservations qui tiennent une place. MÊME garde qu'AffectcarProcessor (endpoint
                            /affectcar) — la réattribution ci-dessous ne compte QUE les billets, pas les
                            réservations : sans ce contrôle, ce chemin PATCH laissait passer un car trop
                            petit pour billets + réservations, et le manque se découvrait à l'émission d'un
                            billet de réservation, face à un client déjà payé. On compare l'occupation
                            maximale d'un segment (un même siège porte plusieurs billets sur des tronçons
                            disjoints), et non un total de billets.
                        */
                        $occupation = $this->capaciteService->occupationMaximale($data, $entrepriseId);
                        if ($occupation > $newCar->getNbrsiege()) {
                            throw new BadRequestHttpException(sprintf(
                                'Ce véhicule (%d place(s)) ne peut pas accueillir les %d place(s) déjà engagées sur ce voyage (billets émis et réservations). Choisissez un véhicule plus grand.',
                                $newCar->getNbrsiege(),
                                $occupation
                            ));
                        }

                        /*
                            Les sièges sont liés au CAR, pas au voyage : au changement de car, les billets
                            pointent des sièges de l'ancien car. On les rattache au nouveau (reprise du
                            numéro, sinon réattribution sur un siège libre — cf. ReaffectationSiegeService).
                            Fait AVANT de basculer les statuts : un refus (car trop petit pour rasseoir tout
                            le monde) ne laisse alors aucun effet de bord.
                        */
                        $this->reaffectationSiege->reaffecter($data, $newCar, $entrepriseId);

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
                        $this->carStatutService->mettreEnVoyage($newCar);

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
            /*
                MÊME garde que la route dédiée /voyages/{id}/affectcar (AffectcarProcessor) : le champ
                'car' est aussi dans 'write:Voyage:update', ce chemin-ci doit donc valoir l'autre.
                L'ancien contrôle comparait getTicketsCount() à la capacité — il ignorait les
                réservations (payées comprises) et, la vente se faisant PAR TRONÇON, comptait plusieurs
                fois un siège revendu sur des tronçons disjoints. On compare l'occupation maximale d'un
                segment, seule grandeur qu'un véhicule doit pouvoir absorber.
            */
            $occupation = $data->getId() === null
                ? 0 // voyage en cours de création : rien n'est encore engagé
                : $this->capaciteService->occupationMaximale($data, (int) $data->getIdentreprise());
            if($occupation > $places) {
                throw new BadRequestHttpException(sprintf(
                    'Ce véhicule (%d place(s)) ne peut pas accueillir les %d place(s) déjà engagées sur ce voyage (billets émis et réservations). Choisissez un véhicule plus grand.',
                    $places,
                    $occupation
                ));
            }
            $data->setPlacesTotal($places);
        }
    }
}

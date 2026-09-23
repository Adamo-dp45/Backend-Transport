<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\CarStatutService;
use App\Domain\Service\NumeroDepartService;
use App\Domain\Service\ReaffectationSiegeService;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Car;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\DBAL\LockMode;
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
        private ReaffectationSiegeService $reaffectationSiege,
        private NumeroDepartService $numeroDepart
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

            /*
                NUMÉRO DE DÉPART DU JOUR (« DÉPART 4 » sur le billet) : attribué SOUS VERROU sur la
                LIGNE et écrit dans la MÊME transaction. Le verrou porte sur la ligne et non sur le
                voyage — qui n'existe pas encore — et sérialise exactement ce qu'il faut : deux gares
                ouvrant au même instant un départ sur la même ligne. Sans lui, toutes deux liraient
                le même « plus haut numéro » et la seconde serait refusée par l'index unique ; le
                verrou lui évite ce refus. La ligne n'est presque jamais écrite, l'attente est donc
                celle d'une insertion.
            */
            return $this->em->wrapInTransaction(function () use ($data, $ligne, $operation, $uriVariables, $context) {
                $this->em->lock($ligne, LockMode::PESSIMISTIC_WRITE);
                $this->numeroDepart->attribuer($data);

                return $this->processor->process($data, $operation, $uriVariables, $context);
            });
        }

        // Posé par le PATCH quand le départ change de JOUR (cf. plus bas) : le numéro est alors repris
        // dans la journée d'accueil, sous le même verrou que la création.
        $renumeroter = false;

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

                    /*
                        CHANGEMENT DE JOUR → le numéro de départ est repris dans la journée d'accueil.

                        Le numéro est FIGÉ à la création, précisément pour qu'un billet déjà remis ne
                        change jamais de départ dans le dos du client. Mais il numérote une JOURNÉE :
                        déplacé au lendemain, « Départ 2 » désigne un car qui n'existe pas encore ce
                        jour-là — ou pire, en désigne un autre, déjà ouvert et déjà vendu sous ce
                        numéro. L'index unique refuserait alors l'écriture. Un simple décalage
                        d'horaire (7 h → 9 h) ne touche à rien : seul le jour compte.

                        Le billet déjà imprimé porte de toute façon une heure devenue fausse — c'est
                        la replanification elle-même qui oblige à rappeler le client, pas ce numéro.
                    */
                    if ($ancienDepart->format('Y-m-d') !== $nouveauDepart->format('Y-m-d')) {
                        $renumeroter = true;
                    }
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
        if ($renumeroter) {
            $ligne = $data->getLigne();
            $ancienNumero = $data->getNumerodepart();

            return $this->em->wrapInTransaction(function () use ($data, $ligne, $ancienNumero, $operation, $uriVariables, $context) {
                $this->em->lock($ligne, LockMode::PESSIMISTIC_WRITE);
                $nouveauNumero = $this->numeroDepart->attribuer($data);

                // Audité : le numéro est imprimé sur des billets. Qui le lit après coup doit pouvoir
                // relier le « Départ 2 » d'hier au « Départ 5 » d'aujourd'hui.
                $this->activiteLogger->voyage(
                    ActiviteLogger::VOYAGE_NUMERO_DEPART,
                    sprintf(
                        'Départ replanifié au %s : numéro %d → %d',
                        $data->getDatedepartprevue()->format('d/m/Y'),
                        $ancienNumero,
                        $nouveauNumero
                    ),
                    $data->getId()
                );

                return $this->processor->process($data, $operation, $uriVariables, $context);
            });
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

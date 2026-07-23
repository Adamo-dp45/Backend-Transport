<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Reservation;
use App\Repository\TarifRepository;
use App\Security\VoyageGuard;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Création d'une réservation de PLACE, PARTAGÉE entre le guichet (agent, {@see App\State\ReservationProcessor})
 * et l'API publique invité (mobile/web, {@see App\Controller\Public\PublicReservationController}).
 *
 * Valide le tronçon, verrouille le prix (grille tarifaire), pose l'échéance de PAIEMENT (la réservation
 * naît impayée — cf. ReservationEcheanceService), vérifie que le car n'a pas déjà quitté la gare de
 * montée puis que la capacité le permet SOUS VERROU pessimiste, rattache une identité client durable et
 * persiste la réservation EN_ATTENTE. Le siège n'est PAS choisi ici (attribué à l'émission du billet).
 */
class ReservationCreationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TarifRepository $tarifRepository,
        private ClientResolver $clientResolver,
        private CapaciteService $capaciteService,
        private ReservationEcheanceService $echeance,
        private VoyageGuard $voyageGuard
    )
    {
    }

    /**
     * @param Reservation $reservation réservation partiellement remplie (voyage, gare montée, garedescente
     *                                 optionnelle, nomclient, contactclient, source déjà positionnés)
     * @param Gare|null   $userGare    gare de l'agent (null pour MOBILE/invité) — restreint la montée sauf MOBILE
     * @param int|null    $createdBy   id de l'agent (null pour un invité)
     */
    public function creer(Reservation $reservation, int $entrepriseId, ?Gare $userGare, ?int $createdBy): Reservation
    {
        $reservation
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($createdBy);

        $voyage = $reservation->getVoyage();
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage obligatoire');
        }
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé, la réservation est impossible');
        }
        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }
        if ($voyage->getDatedepartprevue() === null) {
            throw new BadRequestHttpException('Ce voyage n\'a pas de date de départ');
        }

        /*
            Deux échéances distinctes :
             - PRÉSENTATION : dernière limite pour se présenter au guichet (= limite de réservation) ;
             - PAIEMENT : temps laissé pour payer, compté depuis MAINTENANT (la création), et borné par
               la limite de présentation — inutile de pouvoir payer après le départ du car.
            'dateexpiration' porte l'échéance QUI S'APPLIQUE À CET INSTANT : celle du paiement tant que
            la réservation est impayée ; elle est repoussée à la limite de présentation dès l'encaissement
            (cf. ReservationConfirmationService).
        */
        $now = new \DateTimeImmutable();

        // Gares montée / descente (descente par défaut = terminus)
        $garemontee = $reservation->getGare();
        if (!$garemontee) {
            throw new BadRequestHttpException('La gare de montée est obligatoire');
        }
        $garedescente = $reservation->getGaredescente() ?? $ligne->getGareterminus();
        $reservation->setGaredescente($garedescente);

        /*
            Le car ne doit pas avoir DÉJÀ QUITTÉ la gare de montée. L'échéance de présentation ne
            suffit pas : elle se calcule sur le départ PRÉVU, or un car qui part en avance sans que le
            planning soit rééchelonné laisserait réserver une place à bord d'un véhicule déjà parti.
            Prédicat intermédiaire-aware : réserver pour une gare en AVAL reste évidemment permis.
        */
        $this->voyageGuard->assertMonteeNonDepassee(
            $voyage,
            $garemontee,
            'Le car a déjà quitté ' . $garemontee->getLibelle() . ' : plus de réservation possible sur ce départ.'
        );

        // Un agent rattaché à une gare ne réserve qu'au départ de SA gare (sauf canal MOBILE)
        if ($reservation->getSource() !== 'MOBILE' && $userGare !== null && $garemontee->getId() !== $userGare->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez réserver qu\'au départ de votre gare (' . $userGare->getLibelle() . ')');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $monteeId = $garemontee->getId();
        $descenteId = $garedescente->getId();
        if (!isset($ordreParGare[$monteeId], $ordreParGare[$descenteId])) {
            throw new BadRequestHttpException('La gare de montée ou de descente n\'est pas un arrêt de la ligne');
        }
        $ordreMontee = $ordreParGare[$monteeId];
        $ordreDescente = $ordreParGare[$descenteId];
        if ($ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('La gare de descente doit être située après la gare de montée');
        }
        // DÉPART PARTIEL : pas de réservation avant la provenance réelle du voyage (le car n'y passe pas).
        $origineEff = $voyage->getOrigineEffective();
        $ordreProvenance = $origineEff !== null ? ($ordreParGare[$origineEff->getId()] ?? 0) : 0;
        if ($ordreMontee < $ordreProvenance) {
            throw new BadRequestHttpException('Ce voyage part de ' . ($origineEff?->getLibelle() ?? 'sa gare de provenance') . ' : aucune réservation avant cette gare.');
        }

        /*
            ÉCHÉANCES — calées sur l'heure de passage du car À LA GARE DE MONTÉE, pas sur le départ du
            voyage : réserver Bouaké → Korhogo reste possible après le départ d'Abidjan, puisque le car
            n'est pas encore à Bouaké. Calculé ici, une fois la montée validée comme arrêt de la ligne.

            Deux échéances distinctes :
             - PRÉSENTATION : dernière limite pour se présenter au guichet (= limite de réservation) ;
             - PAIEMENT : temps laissé pour payer, compté depuis MAINTENANT (la création), et borné par
               la limite de présentation — inutile de pouvoir payer une fois le car passé.
            'dateexpiration' porte l'échéance QUI S'APPLIQUE À CET INSTANT : celle du paiement tant que
            la réservation est impayée ; elle est repoussée à la limite de présentation dès
            l'encaissement (cf. ReservationConfirmationService).
        */
        $passage = $this->echeance->heurePassage($voyage, $garemontee);
        if ($this->echeance->limitePresentation($passage, $entrepriseId) <= $now) {
            throw new BadRequestHttpException(sprintf(
                'Trop tard pour réserver : le car passe à %s à %s.',
                $garemontee->getLibelle(),
                $passage->format('H:i')
            ));
        }
        $expiration = $this->echeance->limitePaiement($now, $passage, $entrepriseId);

        // Prix verrouillé depuis la grille tarifaire globale
        $tarif = $this->tarifRepository->findMontant($monteeId, $descenteId, $entrepriseId);
        if (!$tarif) {
            throw new BadRequestHttpException('Aucun tarif défini pour ce trajet (' . $garemontee->getLibelle() . ' → ' . $garedescente->getLibelle() . ')');
        }

        // Rattache une identité client durable (find-or-create par téléphone)
        $reservation->setClient(
            $this->clientResolver->resolve($reservation->getNomclient(), $reservation->getContactclient(), $entrepriseId, $createdBy)
        );

        $reservation
            ->setPrix($tarif->getMontant())
            ->setDateexpiration($expiration)
            ->setEtatpaiement('EN_ATTENTE_PAIEMENT');

        // Capacité vérifiée SOUS VERROU sur le voyage (sérialise réservations + ventes concurrentes)
        return $this->em->wrapInTransaction(function () use ($reservation, $voyage, $ordreMontee, $ordreDescente, $entrepriseId) {
            $this->em->lock($voyage, LockMode::PESSIMISTIC_WRITE);

            $this->capaciteService->assertPlaceDisponible($voyage, $ordreMontee, $ordreDescente, $entrepriseId);

            $reservation->setCode($this->generateCode($entrepriseId));
            $this->em->persist($reservation);

            return $reservation;
        });
    }

    private function generateCode(int $entrepriseId): string
    {
        $count = $this->em->getRepository(Reservation::class)->count([
            'identreprise' => $entrepriseId,
        ]);

        return 'RES-' . date('Y') . '-' . ($count + 1);
    }
}

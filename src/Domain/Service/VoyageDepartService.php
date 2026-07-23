<?php

namespace App\Domain\Service;

use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\CourrierStatus;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Marque le DÉPART RÉEL d'un voyage (le car a bougé) et propage les transitions de statut :
 *  - Courriers du voyage : EN_ATTENTE -> EN_TRANSIT (les colis sont désormais en route)
 *  - Bagages  du voyage : ENREGISTRE -> EMBARQUE  (chargés dans le car au départ)
 *  - Réservations dont la gare de montée est quittée : échéance close (la place se libère, plus de
 *    paiement possible sur un car parti). Les montées en aval sont épargnées : le car y va encore.
 *
 * Idempotent : si le départ réel est déjà posé, ne fait rien. Ne flush pas (le caller flushe).
 * Utilisé par l'action « Démarrer le voyage » ET par le fallback à la 1re réception.
 */
class VoyageDepartService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActiviteLogger $activiteLogger,
        private ReservationEcheanceService $reservationEcheance,
        private PassageService $passageService
    )
    {
    }

    /**
     * @return bool true si le départ vient d'être enregistré (false s'il l'était déjà).
     */
    public function marquerDepart(Voyage $voyage, ?int $userId = null): bool
    {
        if ($voyage->getDatedepartreelle() !== null) {
            return false; // déjà parti
        }

        $now = new \DateTimeImmutable();
        $voyage->setDatedepartreelle($now);
        if ($userId !== null) {
            $voyage->setUpdatedBy($userId);
        }

        // Passage réel : le car QUITTE l'origine effective. C'est le départ de la 1re gare du trajet.
        $this->passageService->marquerDepart($voyage, $voyage->getOrigineEffective(), $now);

        $identreprise = $voyage->getIdentreprise();

        // Courriers EN_ATTENTE -> EN_TRANSIT
        $courriers = $this->em->getRepository(Courrier::class)->findBy([
            'voyage' => $voyage,
            'statut' => CourrierStatus::STATUT_EN_ATTENTE->value,
            'identreprise' => $identreprise,
            'deletedAt' => null,
        ]);
        foreach ($courriers as $courrier) {
            $courrier->setStatut(CourrierStatus::STATUT_EN_TRANSIT->value)->setUpdatedAt($now);
        }

        // Bagages ENREGISTRE -> EMBARQUE
        $bagages = $this->em->getRepository(Bagage::class)->findBy([
            'voyage' => $voyage,
            'statut' => BagageStatus::STATUT_ENREGISTRE->value,
            'identreprise' => $identreprise,
            'deletedAt' => null,
        ]);
        foreach ($bagages as $bagage) {
            $bagage->setStatut(BagageStatus::STATUT_EMBARQUE->value)->setUpdatedAt($now);
        }

        // Le car a quitté sa gare de provenance : les réservations qui devaient y monter n'ont plus
        // d'objet. Appelé APRÈS setDatedepartreelle, dont dépend le calcul de « montée dépassée ».
        $reservations = $this->reservationEcheance->cloturerMonteesDepassees($voyage, $now);

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_DEPART,
            sprintf(
                'Départ du voyage (%d colis en transit, %d bagages embarqués, %d réservation(s) non honorée(s))',
                count($courriers),
                count($bagages),
                $reservations
            ),
            $voyage->getId()
        );

        return true;
    }
}

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
 *
 * Idempotent : si le départ réel est déjà posé, ne fait rien. Ne flush pas (le caller flushe).
 * Utilisé par l'action « Démarrer le voyage » ET par le fallback à la 1re réception.
 */
class VoyageDepartService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ActiviteLogger $activiteLogger
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

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_DEPART,
            sprintf('Départ du voyage (%d colis en transit, %d bagages embarqués)', count($courriers), count($bagages)),
            $voyage->getId()
        );

        return true;
    }
}

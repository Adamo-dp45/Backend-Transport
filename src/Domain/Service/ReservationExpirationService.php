<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Régularise le STATUT des réservations échues (à planifier en cron) :
 *   1. EN_ATTENTE (impayée) échue                    → EXPIREE (définitivement perdue)
 *   2. CONFIRMEE (payée) échue, billet non émis       → A_REGULARISER (récupérable : report + pénalité)
 *   3. A_REGULARISER dont la fenêtre (départ + N j)    → EXPIREE (définitivement perdue)
 *      est dépassée (N configurable par entreprise)
 *
 * La capacité ignore déjà les réservations échues (cf. CapaciteService) ; ceci ne fait que
 * matérialiser les transitions de statut pour l'affichage et la régularisation.
 */
class ReservationExpirationService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationConfigService $config,
        private EntityManagerInterface $em
    )
    {
    }

    /**
     * @return array{expirees:int, aRegulariser:int, expireesDefinitives:int}
     */
    public function traiter(): array
    {
        $now = new \DateTimeImmutable();

        // 1 & 2 : transitions de masse (dépendent seulement de dateexpiration, stockée)
        $expirees = $this->reservationRepository->expirerEnAttenteEchues();
        $aRegulariser = $this->reservationRepository->basculerConfirmeesEnRegularisation();

        // 3 : fenêtre de régularisation dépassée → EXPIREE (fenêtre configurable par entreprise)
        $expireesDefinitives = 0;
        $fenetreParEntreprise = [];
        foreach ($this->reservationRepository->findARegulariser() as $resa) {
            $depart = $resa->getVoyage()?->getDatedepartprevue();
            if ($depart === null) {
                continue;
            }
            $ide = (int) $resa->getIdentreprise();
            $fenetreParEntreprise[$ide] ??= $this->config->getParametre($ide)->getFenetreRegularisationJours();
            $limite = $depart->modify('+' . $fenetreParEntreprise[$ide] . ' days');
            if ($limite < $now) {
                $resa->setStatut(ReservationStatus::STATUT_EXPIREE->value);
                $expireesDefinitives++;
            }
        }
        if ($expireesDefinitives > 0) {
            $this->em->flush();
        }

        return [
            'expirees' => $expirees,
            'aRegulariser' => $aRegulariser,
            'expireesDefinitives' => $expireesDefinitives,
        ];
    }
}

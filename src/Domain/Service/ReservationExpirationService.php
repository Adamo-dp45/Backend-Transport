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
        private EntityManagerInterface $em,
        private ReservationEcheanceService $echeance
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
            $voyage = $resa->getVoyage();
            /*
                Fenêtre comptée depuis le passage du car À LA GARE DU CLIENT, pas depuis le départ du
                voyage : c'est à ce moment-là qu'il a été no-show. Sur une ligne longue, l'ancrer sur
                l'origine amputait la fenêtre de plusieurs heures pour qui montait en cours de route.
            */
            $reference = $voyage === null ? null : $this->echeance->heurePassage($voyage, $resa->getGare());
            if ($reference === null) {
                continue;
            }
            $ide = (int) $resa->getIdentreprise();
            $fenetreParEntreprise[$ide] ??= $this->config->getParametre($ide)->getFenetreRegularisationJours();
            $limite = $reference->modify('+' . $fenetreParEntreprise[$ide] . ' days');
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

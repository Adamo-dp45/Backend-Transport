<?php

namespace App\Entity\Output\ReservationStats;

/**
 * L'ENTONNOIR D'UN CANAL de réservation : émises → payées → présentées, et ce qui se perd en route.
 *
 * 'total' est conservé — c'est le volume, que les écrans existants affichent déjà. Le reste répond à
 * la question que le volume seul ne tranche pas : un canal qui apporte 60 % des bons et n'en honore
 * qu'un tiers ne vaut pas celui qui en apporte 40 % et les honore tous.
 */
final class ReservationParSourceDto
{
    public function __construct(
        public readonly string $source,
        public readonly int $total,
        /** Payées = l'argent est entré : CONFIRMEE + A_REGULARISER (un no-show a bel et bien payé). */
        public readonly int $payees = 0,
        /** Présentées = un billet a été émis. Entre « payée » et « présentée » se loge le no-show. */
        public readonly int $presentees = 0,
        /** Payées mais non présentées — récupérables par régularisation, donc PAS une perte sèche. */
        public readonly int $aRegulariser = 0,
        /** Expirées (jamais payées dans le délai) + annulées : aucune recette. */
        public readonly int $perdues = 0,
        public readonly int $recette = 0,
        /** Part des bons émis qui sont payés : la mesure qui compare deux canaux. */
        public readonly float $tauxConversion = 0.0,
        /** Part des bons PAYÉS dont le client s'est présenté. */
        public readonly float $tauxPresentation = 0.0
    )
    {
    }
}

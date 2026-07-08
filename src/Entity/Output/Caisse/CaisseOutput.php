<?php

namespace App\Entity\Output\Caisse;

final class CaisseOutput
{
    public function __construct(
        public readonly int $totalTickets,
        public readonly float $recetteTickets,
        public readonly int $totalCourriers,
        public readonly float $recetteCourriers,
        public readonly int $totalBagages,
        public readonly float $recetteBagages,
        public readonly float $recetteTotale,
        /** @var CaisseParAgentDto[] */
        public readonly array $parAgent,
        /** @var CaisseParJourDto[] */
        public readonly array $parJour,
        /** @var CaisseParGareDto[] */
        public readonly array $parGare = [],
        // Scission de la recette tickets en 3 CANAUX : gares (guichet, = somme de parGare) vs commerciaux
        // (vente à bord) vs réservations (billets émis depuis un bon, payés sur le compte admin).
        // Ensemble ils totalisent recetteTickets → la page caisse se réconcilie.
        public readonly float $recetteTicketsGares = 0,
        public readonly float $recetteTicketsCommerciaux = 0,
        public readonly float $recetteTicketsReservation = 0,
        // Produit constaté d'AVANCE : bons de réservation PAYÉS mais dont le billet n'est pas encore émis
        // (argent sur le compte admin, hors recette billets). Informatif, ne s'additionne pas aux recettes.
        public readonly int $bonsReservationNonEmisCount = 0,
        public readonly float $bonsReservationNonEmisMontant = 0
    )
    {
    }
}
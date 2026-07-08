<?php

namespace App\Entity\Output\Client;

final class ClientStatistiqueOutput
{
    public function __construct(
        public readonly int $totalClients,
        public readonly int $membres,
        public readonly int $nouveauxClients,   // créés sur la période
        public readonly int $clientsActifs,     // ayant voyagé (billet VALIDE) sur la période
        public readonly int $panierMoyen,       // dépense moyenne par client actif (FCFA, période)
        /** @var TopClientDto[] Clients les plus fidèles (par nombre de voyages) */
        public readonly array $topParBillets,
        /** @var TopClientDto[] Plus gros acheteurs (par dépense) */
        public readonly array $topParDepense
    )
    {
    }
}

<?php

namespace App\Entity\Data;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Dto\PaiementWebhookInput;
use App\Entity\Dto\ReservationPubliqueInput;
use App\Entity\Output\Reservation\CompagniePubliqueDto;
use App\Entity\Output\Reservation\DepartPubliqueDto;
use App\Entity\Output\Reservation\DestinationPubliqueDto;
use App\Entity\Output\Reservation\GarePubliqueDto;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Entity\Output\Reservation\VillePubliqueDto;
use App\State\Public\CompagniePubliqueProvider;
use App\State\Public\DepartsPubliquesProvider;
use App\State\Public\DestinationsPubliquesProvider;
use App\State\Public\GaresPubliquesProvider;
use App\State\Public\HistoriquePubliqueProvider;
use App\State\Public\PaiementWebhookProcessor;
use App\State\Public\ReservationPubliqueProcessor;
use App\State\Public\ReservationSuiviProvider;
use App\State\Public\VillesPubliquesProvider;

/**
 * FAÇADE PUBLIQUE (invité mobile/web) de la réservation — 100 % API Platform (providers/processors),
 * hors authentification (`PUBLIC_ACCESS` ; le firewall `api` laisse passer l'anonyme). Le périmètre
 * entreprise est porté par le paramètre `?slug=` (résolu par EntreprisePubliqueResolver), pas par un
 * utilisateur JWT. Ne renvoie que du JSON de données publiques — AUCUNE vue (le bon/billet est rendu
 * par le FRONT). Ressource non persistée : chaque opération a son provider/processor dédié.
 */
#[ApiResource(
    security: "is_granted('PUBLIC_ACCESS')",
    operations: [
        new Get(
            uriTemplate: '/reservation/compagnie',
            provider: CompagniePubliqueProvider::class,
            input: false,
            output: CompagniePubliqueDto::class,
            openapi: new Operation(summary: 'Compagnie (branding public) — ?slug=')
        ),
        new GetCollection(
            uriTemplate: '/reservation/villes',
            provider: VillesPubliquesProvider::class,
            paginationEnabled: false,
            output: VillePubliqueDto::class,
            openapi: new Operation(summary: 'Villes desservies — ?slug=')
        ),
        new GetCollection(
            uriTemplate: '/reservation/gares',
            provider: GaresPubliquesProvider::class,
            paginationEnabled: false,
            output: GarePubliqueDto::class,
            openapi: new Operation(summary: 'Gares d\'une ville — ?slug=&ville=')
        ),
        new GetCollection(
            uriTemplate: '/reservation/destinations',
            provider: DestinationsPubliquesProvider::class,
            paginationEnabled: false,
            output: DestinationPubliqueDto::class,
            openapi: new Operation(summary: 'Destinations + tarifs depuis une gare — ?slug=&gare=')
        ),
        new GetCollection(
            uriTemplate: '/reservation/departs',
            provider: DepartsPubliquesProvider::class,
            paginationEnabled: false,
            output: DepartPubliqueDto::class,
            openapi: new Operation(summary: 'Départs réservables — ?slug=&provenance=&destination=')
        ),
        new Post(
            uriTemplate: '/reservation/reservations',
            processor: ReservationPubliqueProcessor::class,
            input: ReservationPubliqueInput::class,
            output: ReservationPubliqueDto::class,
            openapi: new Operation(summary: 'Créer une réservation invité + initier le paiement — ?slug=')
        ),
        new Get(
            uriTemplate: '/reservation/suivi',
            provider: ReservationSuiviProvider::class,
            input: false,
            output: ReservationPubliqueDto::class,
            openapi: new Operation(summary: 'Suivi d\'une réservation — ?slug=&code=&contact=')
        ),
        new GetCollection(
            uriTemplate: '/reservation/historique',
            provider: HistoriquePubliqueProvider::class,
            paginationEnabled: false,
            output: ReservationPubliqueDto::class,
            openapi: new Operation(summary: 'Historique des réservations d\'un client — ?slug=&contact=')
        ),
        new Post(
            uriTemplate: '/reservation/paiement/webhook',
            processor: PaiementWebhookProcessor::class,
            input: PaiementWebhookInput::class,
            output: false,
            openapi: new Operation(summary: 'Webhook de paiement (prestataire ou simulation)')
        ),
    ]
)]
class ReservationPublique
{
}

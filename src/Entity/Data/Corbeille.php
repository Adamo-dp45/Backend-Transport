<?php

namespace App\Entity\Data;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Output\Corbeille\CorbeilleListeDto;
use App\State\Corbeille\CorbeilleEmptyProvider;
use App\State\Corbeille\CorbeilleProvider;
use App\State\Corbeille\CorbeillePurgerProcessor;
use App\State\Corbeille\CorbeilleRestaurerLotProcessor;
use App\State\Corbeille\CorbeilleRestaurerProcessor;
use App\State\Corbeille\CorbeilleViderLotProcessor;

/**
 * CORBEILLE — espace SUPER ADMIN, MULTI-ENTREPRISES. Ressource NON persistée (chaque opération a son
 * provider/processor dédié, cf. `ReservationPublique`). Réservée au super admin (hors périmètre
 * entreprise) : il voit et gère les enregistrements soft-deletés de TOUTES les compagnies.
 *
 * Note API Platform : une opération statique sans variable d'URI est confondue avec une collection ;
 * les actions de LOT utilisent donc `Post` (pas `Patch`/`Delete`), avec un provider « vide ».
 */
#[ApiResource(
    security: "is_granted('ROLE_SUPER_ADMIN')",
    securityMessage: 'Seul le super administrateur peut accéder à la corbeille.',
    operations: [
        new Get(
            uriTemplate: '/corbeille',
            provider: CorbeilleProvider::class,
            input: false,
            output: CorbeilleListeDto::class,
            normalizationContext: ['groups' => ['read:Corbeille'], 'skip_null_values' => false],
            openapi: new Operation(
                summary: 'Corbeille multi-entreprises (super admin)',
                description: 'Éléments soft-deletés de toutes les compagnies + compteurs. Filtres : ?type[]=ticket&entreprise=3',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            uriTemplate: '/corbeille/{type}/{id}/restaurer',
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeilleRestaurerProcessor::class,
            input: false,
            openapi: new Operation(
                summary: 'Restaurer un élément',
                security: [['bearerAuth' => []]]
            )
        ),
        new Delete(
            uriTemplate: '/corbeille/{type}/{id}',
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeillePurgerProcessor::class,
            input: false,
            openapi: new Operation(
                summary: 'Suppression définitive d\'un élément',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            uriTemplate: '/corbeille/restaurer-tout',
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeilleRestaurerLotProcessor::class,
            openapi: new Operation(
                summary: 'Restaurer tout',
                description: 'Filtres : ?type[]=ticket&entreprise=3 (sinon toute la corbeille)',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            uriTemplate: '/corbeille/vider',
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeilleViderLotProcessor::class,
            openapi: new Operation(
                summary: 'Vider (suppression définitive en lot)',
                description: 'Filtres : ?type[]=ticket&entreprise=3 (sinon toute la corbeille)',
                security: [['bearerAuth' => []]]
            )
        ),
    ]
)]
class Corbeille
{
}

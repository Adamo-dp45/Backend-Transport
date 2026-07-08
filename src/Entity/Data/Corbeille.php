<?php

namespace App\Entity\Data;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\State\CorbeilleDeleteProcessor;
use App\State\CorbeilleEmptyProvider;
use App\State\CorbeilleProvider;
use App\State\CorbeilleRestaurerProcessor;
use App\State\CorbeilleRestaurerToutProcessor;
use App\State\CorbeilleViderProcessor;

#[ApiResource(
    security: "is_granted('ROLE_ADMIN')",
        operations: [
        new GetCollection(
            uriTemplate: '/corbeille',
            provider: CorbeilleProvider::class,
            openapi: new Operation(
                summary: 'Liste des éléments en corbeille',
                description: 'Filtre par type : ?type[]=car[]=courrier',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            uriTemplate: '/corbeille/{type}/{id}/restaurer',
            provider: CorbeilleEmptyProvider::class, /*
                - On provider vide sinon il va lire l'entité vu que le processor attend un '$data', le 'read: false' ne fonctionne pas aussi
            */
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
            processor: CorbeilleDeleteProcessor::class,
            input: false,
            openapi: new Operation(
                summary: 'Suppression définitive d\'un élément',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            uriTemplate: '/corbeille/restaurer', /*
                - Vu qu'on utilise une entité vide, si on n'a pas de paramètre dans l'url 'ApiPlatform' ne peut pas distinguer '/corbeille/restaurer' d'un 'GetCollection', il confond les routes statiques sans variables avec la collection donc la solution est d'utiliser 'Post' au lieu de 'Patch' ou 'Delete' pour les opérations sans paramètres
            */
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeilleRestaurerToutProcessor::class,
            openapi: new Operation(
                summary: 'Restaurer tous les éléments d\'un type',
                description: 'Filtre par type : ?type=ticket',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            uriTemplate: '/corbeille/vider',
            provider: CorbeilleEmptyProvider::class,
            processor: CorbeilleViderProcessor::class,
            openapi: new Operation(
                summary: 'Vider la corbeille d\'un type',
                description: 'Filtre par type : ?type=ticket',
                security: [['bearerAuth' => []]]
            )
        )
    ]
)]
class Corbeille
{
}
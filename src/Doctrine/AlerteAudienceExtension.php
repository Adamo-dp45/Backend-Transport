<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Domain\Service\AlerteAudienceResolver;
use App\Entity\Alerte;
use Doctrine\ORM\QueryBuilder;

/**
 * Ciblage (audience) des alertes appliqué au pipeline API Platform, EN PLUS du périmètre
 * entreprise (EntrepriseScopeExtension) et du statut. Comme toutes les extensions de requête
 * sont génériques (autoconfigure), on borne STRICTEMENT à la ressource Alerte. La règle
 * elle-même vit dans AlerteAudienceResolver (partagée avec les contrôleurs).
 */
class AlerteAudienceExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private AlerteAudienceResolver $audience)
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->apply($resourceClass, $queryBuilder);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void {
        $this->apply($resourceClass, $queryBuilder);
    }

    private function apply(string $resourceClass, QueryBuilder $queryBuilder): void
    {
        if ($resourceClass !== Alerte::class) {
            return;
        }
        $alias = $queryBuilder->getAllAliases()[0];
        $this->audience->appliquer($queryBuilder, $alias);
    }
}

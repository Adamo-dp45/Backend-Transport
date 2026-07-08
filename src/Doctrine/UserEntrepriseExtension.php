<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class UserEntrepriseExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private Security $security
    )
    {
    }

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($resourceClass, $queryBuilder);
    }

    public function applyToItem(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        array $identifiers,
        ?Operation $operation = null,
        array $context = []
    ): void
    {
        $this->addWhere($resourceClass, $queryBuilder);
    }

    private function addWhere(string $resourceClass, QueryBuilder $queryBuilder)
    {
        if($resourceClass !== User::class) {
            return;
        }
        /**
         * @var User
         */
        $user = $this->security->getUser();

        if(!$user || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $alias = $queryBuilder->getAllAliases()[0];
        $queryBuilder
            ->andWhere("$alias.entreprise = :entreprise") // Ou.. 'sprintf('%s.entreprise = :entreprise', $alias)'
            ->setParameter('entreprise', $user->getEntreprise()->getId());
            // ->setParameter('entreprise', $user->getEntrepriseid());
    }
}
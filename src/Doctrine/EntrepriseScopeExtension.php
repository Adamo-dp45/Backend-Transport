<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class EntrepriseScopeExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface /*
    - On n'a pas besoin d'enregistrer le service 'services.yaml' car il est fais automatiquement
    - Vu qu'il est générique il va agir sur toutes les requêtes 'getCollection' et 'get', 'put', 'patch' et 'delete' mais pas sur 'post'
*/
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
        /**
         * @var User
         */
        $user = $this->security->getUser();
        if(!$user || $this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }
        /*
            $reflectionClass = new ReflectionClass($resourceClass); -- Peut être impactant donc on peut le mettre en cache
                - 'if($resourceClass === Post::class)' Ou..
                - 'if($reflectionClass->implementsInterface(EntrepriseOwnedInterface::class))'
        */
        if(is_subclass_of($resourceClass, EntrepriseOwnedInterface::class)) { /*
            - Le 'is_subclass_of' est optimisé par 'php' plus que 'ReflectionClass'
        */
            $entrepriseId = $user->getEntreprise()->getId();
            if($entrepriseId !== null) {
                $alias = $queryBuilder->getAllAliases()[0]; /*
                    - Permet de récupérer le premier alias
                */
                $queryBuilder
                    ->andWhere("$alias.identreprise = :entrepriseId")
                    ->andWhere("$alias.deletedAt IS NULL")
                    ->setParameter('entrepriseId', $entrepriseId);
            } else {
                /*
                    - Pour ne pas bloqué le super administrateur pour les opérations personnalisé mais on l'a fait au debut en vérifiant le rôle
                */
            }
        }
    }
    /*
        private static array $reflectionCache = [];
        private function implementsEntrepriseOwned(string $resourceClass): bool -- Le cache pour le 'ReflectionClass'
        {
            if(!isset(self::$reflectionCache[$resourceClass])) {
                self::$reflectionCache[$resourceClass] = (new ReflectionClass($resourceClass))->implementsInterface(EntrepriseOwnedInterface::class);
            }
            return self::$reflectionCache[$resourceClass];
        }
    */
}
<?php

namespace App\Repository;

use App\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function hasPermission(
        array $roles,
        string $entityName,
        string $action,
        int $entrepriseId
    ): bool
    {
        return (bool) $this->createQueryBuilder('p')
            ->select('1')
            ->where('p.role IN (:roles)')
            ->andWhere('p.entity = :entity')
            ->andWhere('p.action = :action')
            ->andWhere('p.identreprise = :entreprise')
            ->setParameter('roles', $roles)
            ->setParameter('entity', $entityName)
            ->setParameter('action', $action)
            ->setParameter('entreprise', $entrepriseId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        ;
    }

    //    /**
    //     * @return Permission[] Returns an array of Permission objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Permission
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

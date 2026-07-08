<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findWithRolesAndPermissions(int $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.userRoles', 'ur')
            ->leftJoin('ur.role', 'r')
            ->leftJoin('r.permissions', 'p')
            ->leftJoin('u.entreprise', 'e')
            ->leftJoin('u.gare', 'g')
            ->addSelect('ur', 'r', 'p', 'e', 'g')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /* Inventaire
     */
    public function findInfosByIds(array $ids): array
    {
        if(empty($ids)) {
            return [];
        }
        $rows = $this->createQueryBuilder('u')
            ->select('u.id, u.nom, u.prenom')
            ->andWhere('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getArrayResult()
        ;
        $index = []; /*
            - On.. index par 'id' pour lookup rapide
        */
        foreach($rows as $row) {
            $index[$row['id']] = $row;
        }
        return $index;
    }

    /* Statistiques
     */
    public function countTotal(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.entreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByEntreprise(int $identreprise): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.id, u.nom, u.prenom')
            ->andWhere('u.entreprise = :ide')
            ->setParameter('ide', $identreprise)
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /** Nombre d'agents ACTIFS rattachés à chaque gare (headcount courant, pas de période). */
    public function countActifsParGare(int $identreprise): array
    {
        return $this->createQueryBuilder('u')
            ->select('IDENTITY(u.gare) AS gareid, COUNT(u.id) AS nb')
            ->andWhere('u.entreprise = :ide')
            ->andWhere('u.gare IS NOT NULL')
            ->andWhere("u.statut = 'ACTIF'")
            ->setParameter('ide', $identreprise)
            ->groupBy('u.gare')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

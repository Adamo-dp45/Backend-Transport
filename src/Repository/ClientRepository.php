<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Retrouve un client ACTIF par son téléphone (clé naturelle) au sein d'une entreprise.
     * Sert au find-or-create au point de vente (cf. App\Domain\Service\ClientResolver).
     */
    public function findOneActifByContact(string $contact, int $identreprise): ?Client
    {
        return $this->findOneBy([
            'contact' => $contact,
            'identreprise' => $identreprise,
            'deletedAt' => null,
        ]);
    }

    // -- Statistiques fidélité -- //

    /** Nombre de clients actifs de l'entreprise. */
    public function countActifs(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nouveaux clients créés sur la période. */
    public function countNouveaux(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.createdAt >= :debut')
            ->andWhere('c.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nombre de clients membres du programme de fidélité. */
    public function countMembres(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.fidelite = true')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Nouvelles adhésions sur la période (date d'adhésion comprise). */
    public function countAdhesions(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.fidelite = true')
            ->andWhere('c.dateadhesion >= :debut')
            ->andWhere('c.dateadhesion <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Membres du programme (pour le calcul de l'état par membre).
     * @return array<int, array{id:int, nom:string, contact:?string, dateadhesion:?\DateTimeInterface}>
     */
    public function findMembres(int $identreprise): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id', 'c.nom', 'c.contact', 'c.dateadhesion')
            ->andWhere('c.identreprise = :ide')
            ->andWhere('c.deletedAt IS NULL')
            ->andWhere('c.fidelite = true')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getArrayResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Bagage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bagage>
 */
class BagageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bagage::class);
    }

    /* Statistiques
     * Pilotées par le STATUT métier (et non par 'deletedAt') : la corbeille ne gère que la visibilité
     * dans les listes, pas l'historique comptable. La recette ne compte que les bagages réellement pris en
     * charge (EMBARQUE/LIVRE/PERDU) ; un bagage seulement ENREGISTRE n'a pas encore généré de recette.
     */

    public function recettesTotales(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): float
    {
        $row = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.montant), 0) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult()
        ;

        return round((float)($row['total'] ?? 0), 2);
    }

    public function recettesParAgent(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.createdBy AS agentid, COALESCE(SUM(b.montant), 0) AS montant, COUNT(b.id) AS nbbagages')
            ->join('b.ticket', 't') // canal dérivé du billet
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('t.commercial IS NULL') // guichet : bagages enregistrés par le commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.createdBy')
            ->getQuery()
            ->getArrayResult()
        ;
    }

    public function recettesParJourDetail(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'DATE(b.createdAt) AS label',
                'COALESCE(SUM(b.montant), 0) AS montant',
                'COUNT(b.id) AS nbbagages',
                'COALESCE(SUM(b.poids), 0) AS poids',
            )
            ->join('b.ticket', 't') // canal dérivé du billet
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('t.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('label')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /* Statistiques Bagage
     */
    public function countParStatut(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array
    {
        $rows = $this->createQueryBuilder('b')
            ->select('b.statut, COUNT(b.id) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('b.statut')
            ->getQuery()
            ->getArrayResult()
        ;
        $index = [];
        foreach($rows as $row) {
            $index[$row['statut']] = (int)$row['total'];
        }
        return $index;
    }

    public function poidsTotal(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): int
    {
        $row = $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.poids), 0) AS total')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleResult()
        ;
        return (int)($row['total'] ?? 0);
    }

    /**
     * Recette bagage groupée par gare de DÉPART (gare d'émission). Compté à la création.
     * Les bagages sans gare de départ sont exclus (jointure interne) ; seuls les statuts pris en charge comptent.
     */
    public function recetteParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nbbagages, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')
            ->join('b.ticket', 't') // canal dérivé du billet
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('t.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette bagages par gare (dépôt) ET par jour — séries temporelles / sparklines. */
    public function recetteParGareEtJour(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, DATE(b.createdAt) AS jour, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')
            ->join('b.ticket', 't') // canal dérivé du billet
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('t.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('jour')
            ->getQuery()
            ->getArrayResult();
    }

    /** Recette bagages par gare (dépôt) ET par agent (createdBy) — croisement caisse. */
    public function recetteParGareEtAgent(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, b.createdBy AS agentid, COUNT(b.id) AS nb, COALESCE(SUM(b.montant), 0) AS recette')
            ->join('b.garedepart', 'g')
            ->join('b.ticket', 't') // canal dérivé du billet
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('t.commercial IS NULL') // guichet : bagages du commercial exclus (recette à part)
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['ENREGISTRE', 'EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->addGroupBy('b.createdBy')
            ->getQuery()
            ->getArrayResult();
    }

    /** Bagages émis par gare (garedepart) : nombre + poids total expédié. */
    public function emisParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nb, COALESCE(SUM(b.poids), 0) AS poids')
            ->join('b.garedepart', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut IN (:statuts)')
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('statuts', ['EMBARQUE', 'LIVRE', 'PERDU'])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /** Bagages livrés par gare (garedescente, statut LIVRE). */
    public function livresParGare(\DateTimeImmutable $debut, \DateTimeImmutable $fin, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select('g.id AS gareid, g.libelle AS garelibelle, COUNT(b.id) AS nb')
            ->join('b.garedescente', 'g')
            ->andWhere('b.identreprise = :ide')
            ->andWhere("b.statut = 'LIVRE'")
            ->andWhere('b.createdAt >= :debut')
            ->andWhere('b.createdAt <= :fin')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('g.id')
            ->getQuery()
            ->getArrayResult();
    }

    /* Bordereau chauffeur
     */
    public function findByVoyage(int $voyageId, int $identreprise): array
    {
        return $this->createQueryBuilder('b')
            ->select(
                'b.codebagage',
                't.nomclient AS nomclient', // identité reprise du billet rattaché (colonne bagage supprimée)
                'b.nature',
                'b.type',
                'b.poids',
                'b.montant',
            )
            ->leftJoin('b.ticket', 't')
            ->andWhere('b.voyage = :voyageId')
            ->andWhere('b.identreprise = :ide')
            ->andWhere('b.statut != :perdu')
            ->andWhere('b.deletedAt IS NULL')
            ->setParameter('voyageId', $voyageId)
            ->setParameter('ide', $identreprise)
            ->setParameter('perdu', 'PERDU')
            ->orderBy('b.codebagage', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    //    /**
    //     * @return Bagage[] Returns an array of Bagage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Bagage
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

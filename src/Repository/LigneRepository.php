<?php

namespace App\Repository;

use App\Entity\Ligne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ligne>
 */
class LigneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ligne::class);
    }

    // -- Statistiques (mirror de TrajetRepository, groupé sur la ligne) -- //

    public function countTotal(int $identreprise): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.identreprise = :ide')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findAllAvecStats(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        int $identreprise
    ): array {
        return $this->createQueryBuilder('l')
            ->select(
                'l.id',
                'l.libelle',
                'l.codeligne',
                'COUNT(DISTINCT v.id) AS nbvoyages',
                'COUNT(t.id) AS nbtickets',
                'COALESCE(SUM(t.prix), 0) AS recette',
            )
            ->leftJoin('l.voyages', 'v', 'WITH', 'v.datedepartprevue >= :debut AND v.datedepartprevue <= :fin AND v.deletedAt IS NULL')
            // Billets VALIDE seulement (exclut les désistés reportés/annulés) et HORS réservation (recette
            // réservation reconnue au paiement, ajoutée séparément par LigneStatsProvider) — anti double-comptage.
            /*
                !! 't.deletedAt IS NULL' EST INDISPENSABLE, et il manquait. Le voyage filtrait bien sa
                corbeille ('v.deletedAt'), le billet non : un billet mis à la corbeille continuait de
                porter sa recette sur sa ligne. Mesuré le 08/10/2026 sur la base de dev — la ligne
                « Korhogo → Abidjan », qui n'a JAMAIS rien vendu, affichait 11 000 F de recette et
                11 000 F de résultat pour un unique billet supprimé ; et la somme des recettes par
                ligne dépassait la recette totale de l'entreprise, ce qu'un sous-ensemble ne peut pas
                faire. C'est la règle de TOUT total d'argent du projet : la corbeille dit que la ligne
                n'aurait jamais dû être saisie, sa recette non plus.
            */
            ->leftJoin('v.tickets', 't', 'WITH', "t.createdAt >= :debut AND t.createdAt <= :fin AND t.statut = 'VALIDE' AND t.reservation IS NULL AND t.deletedAt IS NULL")
            ->andWhere('l.identreprise = :ide')
            ->andWhere('l.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->groupBy('l.id') // l.libelle / l.codeligne sont fonctionnellement dépendants de la PK (MySQL 8)
            ->orderBy('recette', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}

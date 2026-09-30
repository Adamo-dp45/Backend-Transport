<?php

namespace App\Repository;

use App\Domain\Enum\SessioncaisseStatut;
use App\Entity\Sessioncaisse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sessioncaisse>
 *
 * !! CE REPOSITORY NE CALCULE AUCUN TOTAL D'ARGENT. Les huit postes du théorique sont figés sur la
 * session à la clôture (cf. 'Sessioncaisse'), et c'est 'CaisseTheoriqueService' (palier 2) qui les
 * compose depuis les entités de vente. Ajouter ici une somme qui recalculerait un poste après coup
 * donnerait deux chiffres pour la même caisse, dont un seul aurait été signé par l'agent.
 */
class SessioncaisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sessioncaisse::class);
    }

    /**
     * La caisse OUVERTE d'un agent, s'il en a une.
     *
     * Bornée à l'entreprise bien que l'agent lui appartienne déjà : c'est la même paire de colonnes
     * que l'index unique, donc la requête le suit au lieu de compter dessus.
     *
     * 'deletedAt IS NULL' par discipline : aucun filtre Doctrine global n'existe dans ce projet, et
     * une session n'a de toute façon aucune raison d'aller à la corbeille — elle en est exclue
     * (on ne met pas une preuve à la corbeille). Le filtre est là pour que la règle soit écrite
     * partout pareil, pas parce que le cas se produit.
     */
    public function ouvertePourAgent(int $agentId, int $identreprise): ?Sessioncaisse
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.agent = :agent')
            ->andWhere('s.identreprise = :identreprise')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('agent', $agentId)
            ->setParameter('identreprise', $identreprise)
            ->setParameter('statut', SessioncaisseStatut::OUVERTE->value)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Les caisses ENCORE OUVERTES au-delà d'un âge donné — un agent parti sans compter.
     *
     * Son tiroir n'est plus opposable à personne : chaque journée qui passe éloigne du moment où
     * l'on aurait pu constater l'écart, et les ventes du lendemain viennent s'y ajouter.
     *
     * @return list<Sessioncaisse>
     */
    public function ouvertesAvant(\DateTimeImmutable $limite, int $identreprise): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.identreprise = :ide')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.datedebut < :limite')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('statut', SessioncaisseStatut::OUVERTE->value)
            ->setParameter('limite', $limite)
            ->orderBy('s.datedebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les caisses clôturées sur un ÉCART important, dans un sens comme dans l'autre.
     *
     * !! LES ATTENDUS NÉGATIFS SONT ÉCARTÉS, et c'est indispensable : quand il est sorti de la
     * caisse plus d'argent qu'il n'y est entré, l'écart vaut la totalité du réapprovisionnement
     * non saisi — un faux « excédent » de plusieurs dizaines de milliers de francs. Les laisser
     * passer ferait sonner l'alerte anti-fraude sur un chiffre dont l'écran dit lui-même qu'il ne
     * mesure rien, et trois alertes fausses suffisent à ce qu'on cesse de les lire.
     *
     * @return list<Sessioncaisse>
     */
    public function ecartsElevesDepuis(int $seuil, \DateTimeImmutable $debut, int $identreprise): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.identreprise = :ide')
            ->andWhere('s.statut = :statut')
            ->andWhere('s.datefin >= :debut')
            ->andWhere('ABS(s.ecart) >= :seuil')
            ->andWhere('s.montanttheorique >= 0')
            ->andWhere('s.deletedAt IS NULL')
            ->setParameter('ide', $identreprise)
            ->setParameter('statut', SessioncaisseStatut::CLOTUREE->value)
            ->setParameter('debut', $debut)
            ->setParameter('seuil', $seuil)
            ->orderBy('s.datefin', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

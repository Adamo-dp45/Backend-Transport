<?php

namespace App\DataFixtures;

use App\Domain\Enum\SessioncaisseStatut;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Sessioncaisse;
use App\Entity\Ticket;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * QUATRE CAISSES, une par situation que les écrans doivent savoir montrer.
 *
 * Sans elles, la page des caisses est vide au premier chargement et l'on ne découvre les cas
 * limites qu'en les reproduisant à la main — c'est ainsi qu'on livre un écran qui n'a jamais vu
 * autre chose qu'un compte juste.
 *
 * !! LES TOTAUX NE SONT PAS INVENTÉS : chaque caisse close rattache de VRAIS billets du jeu et son
 * théorique en est la somme. Poser des montants plausibles sans les billets derrière donnerait un
 * décompte que rien ne justifie — précisément le genre de chiffre que ce module existe pour
 * éliminer, et le premier écran de détail le montrerait.
 *
 * DÉTERMINISTE comme le reste : aucun 'rand()', les billets sont pris dans l'ordre des
 * identifiants.
 */
class CaisseFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    public function load(ObjectManager $manager): void
    {
        $entreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class);
        $gare = $this->getReference(Refs::gare(Refs::IRA, 'abidjan'), Gare::class);
        $agent = $this->getReference(Refs::user(Refs::IRA, 'agent-abidjan'), User::class);
        $chef = $this->getReference(Refs::user(Refs::IRA, 'chef-abidjan'), User::class);

        /*
            Les billets de la gare, VALIDES et vendus au guichet (ni commercial, ni réservation) :
            ce sont les seuls qui seraient tombés dans une caisse. En prendre d'autres ferait un
            théorique que le module lui-même ne saurait pas recalculer.
        */
        $billets = $manager->getRepository(Ticket::class)->createQueryBuilder('t')
            ->andWhere('t.identreprise = :ide')
            ->andWhere('t.gare = :gare')
            ->andWhere("t.statut = 'VALIDE'")
            ->andWhere('t.commercial IS NULL')
            ->andWhere('t.reservation IS NULL')
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ide', $entreprise->getId())
            ->setParameter('gare', $gare)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(9)
            ->getQuery()
            ->getResult();

        // 1) La journée ordinaire : le compte tombe juste. C'est l'état normal, il doit exister.
        $this->caisse(
            $manager,
            $entreprise,
            $gare,
            $agent,
            debut: new DateTimeImmutable('-3 days 06:30'),
            fin: new DateTimeImmutable('-3 days 18:45'),
            fonds: 10000,
            billets: array_slice($billets, 0, 4),
            motif: null
        );

        // 2) Le manquant motivé : l'écart signé, le cas pour lequel le module existe.
        $this->caisse(
            $manager,
            $entreprise,
            $gare,
            $chef,
            debut: new DateTimeImmutable('-2 days 06:15'),
            fin: new DateTimeImmutable('-2 days 19:10'),
            fonds: 10000,
            billets: array_slice($billets, 4, 3),
            motif: 'Monnaie rendue en trop sur deux ventes du matin',
            manquant: 2500
        );

        /*
            3) L'ATTENDU NÉGATIF : un remboursement payé avec des espèces venues du coffre, que rien
            ne permet encore de saisir. L'écart affiché vaut alors la totalité du réapprovisionnement
            — un faux « excédent » — et les écrans doivent le signaler comme non exploitable. Sans ce
            cas dans le jeu, ce repère ne se voit jamais avant de tomber dessus en production.
        */
        $annule = $manager->getRepository(Ticket::class)->createQueryBuilder('t')
            ->andWhere('t.identreprise = :ide')
            ->andWhere("t.statut = 'ANNULE'")
            ->andWhere('t.deletedAt IS NULL')
            ->setParameter('ide', $entreprise->getId())
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $this->caisse(
            $manager,
            $entreprise,
            $gare,
            $agent,
            debut: new DateTimeImmutable('-1 day 07:00'),
            fin: new DateTimeImmutable('-1 day 17:30'),
            fonds: 0,
            billets: [],
            motif: 'Remboursement payé sur le coffre de la gare',
            /*
                Le remboursement est RATTACHÉ à un vrai billet annulé du jeu, et son montant en est
                le prix : un poste « remboursements » que rien ne justifie serait exactement le
                chiffre introuvable que ce module combat.
            */
            billetRembourse: $annule,
            /*
                Le TIROIR EST VIDE, il ne peut pas l'être à moitié moins que rien. C'est ce
                comptage réaliste face à un attendu négatif qui produit le faux « excédent » que
                les écrans doivent signaler.
            */
            compte: 0
        );

        /*
            4) La caisse EN COURS, ouverte par une vente : l'agent n'a pas pris de fonds. Ni totaux
            ni écart — ils n'existent qu'à la clôture —, et c'est elle que l'alerte
            'CAISSE_NON_CLOTUREE' finira par signaler si personne ne la ferme.
        */
        $ouverte = (new Sessioncaisse())
            ->setAgent($agent)
            ->setGare($gare)
            ->setDatedebut(new DateTimeImmutable('-2 hours'))
            ->setFondsouverture(0)
            ->setOuvertureautomatique(true)
            ->setStatut(SessioncaisseStatut::OUVERTE->value)
            ->setAgentsessionouverte($agent->getId());
        $ouverte->setIdentreprise((int) $entreprise->getId())->setCreatedBy($agent->getId());
        $manager->persist($ouverte);

        $manager->flush();
    }

    /**
     * Une caisse CLÔTURÉE, dont le théorique est la somme de ses vraies pièces.
     *
     * @param list<Ticket> $billets
     */
    private function caisse(
        ObjectManager $manager,
        Entreprise $entreprise,
        Gare $gare,
        User $agent,
        DateTimeImmutable $debut,
        DateTimeImmutable $fin,
        int $fonds,
        array $billets,
        ?string $motif,
        int $manquant = 0,
        ?Ticket $billetRembourse = null,
        ?int $compte = null,
    ): Sessioncaisse {
        $session = (new Sessioncaisse())
            ->setAgent($agent)
            ->setGare($gare)
            ->setDatedebut($debut)
            ->setDatefin($fin)
            ->setFondsouverture($fonds)
            ->setOuvertureautomatique(false)
            ->setStatut(SessioncaisseStatut::CLOTUREE->value)
            /*
                NULL À LA CLÔTURE : cette colonne porte l'index unique « une seule caisse ouverte
                par agent ». La laisser renseignée interdirait à l'agent d'en rouvrir une, et le
                refus tomberait au milieu d'une vente.
            */
            ->setAgentsessionouverte(null);
        $session->setIdentreprise((int) $entreprise->getId())->setCreatedBy($agent->getId());
        $manager->persist($session);
        $manager->flush(); // L'identifiant est nécessaire pour rattacher les billets.

        $total = 0;
        foreach ($billets as $billet) {
            $billet->setSessioncaisse($session);
            $total += (int) $billet->getPrix();
        }

        $rembourse = 0;
        if ($billetRembourse !== null) {
            $rembourse = (int) $billetRembourse->getPrix();
            $billetRembourse
                ->setMontantrembourse($rembourse)
                ->setSessioncaisseremboursement($session);
        }

        $theorique = $fonds + $total - $rembourse;
        $montantcompte = $compte ?? ($theorique - $manquant);

        $session
            ->setTotalbillets($total)
            ->setTotalbagages(0)
            ->setTotalcourriers(0)
            ->setTotalfraissuivi(0)
            ->setTotalreservations(0)
            ->setTotalpenalites(0)
            ->setTotalcomplements(0)
            ->setTotalremboursements($rembourse)
            ->setMontanttheorique($theorique)
            ->setMontantcompte($montantcompte)
            ->setEcart($montantcompte - $theorique)
            ->setMotifecart($montantcompte !== $theorique ? $motif : null);

        return $session;
    }

    public function getDependencies(): array
    {
        return [BilletterieFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\Depense;
use App\Entity\Entreprise;
use App\Entity\Fournisseur;
use App\Entity\Gare;
use App\Entity\Typedepense;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les charges d'exploitation des deux compagnies.
 *
 * DEUX PROFILS OPPOSÉS : IRA dépense environ le tiers de ce qu'elle encaisse, avec une gare
 * (Korhogo) qui dépense sans rien vendre et ressort donc EN PERTE ; SAHEL dépense plus que ses
 * maigres recettes. Sans ce contraste, on ne verrait jamais un résultat négatif — c'est-à-dire
 * jamais l'écran qui donne son sens au module, celui où la réponse à « est-ce que je gagne de
 * l'argent ? » est « non ».
 *
 * !! LE BÉNÉFICE GLOBAL D'IRA RESTE NÉGATIF, et ce n'est pas le fait de ces dépenses : le jeu de
 * démonstration achète pour ~5,8 millions de pièces détachées et ~1,9 million de dépannages pour
 * ~1,9 million de recettes mensuelles. Ce déséquilibre-là vient de 'StockFixtures' et
 * 'DepannageFixtures', pas d'ici — le corriger demanderait de revoir leur échelle.
 *
 * DÉTERMINISTE, comme le reste des fixtures : aucun 'rand()'. Les montants dérivent de l'index du
 * jour, si bien qu'un écart de calcul se reproduit d'un chargement à l'autre.
 *
 * !! MONTANTS CALIBRÉS SUR LE JEU, PAS SUR LE TERRAIN. Un vrai transporteur met plusieurs dizaines
 * de milliers de francs de gasoil par départ ; ici la démonstration n'encaisse que ~2,6 millions sur
 * le mois, et des charges réalistes écraseraient tous les écrans — le carburant à lui seul pèserait
 * cinq fois la recette. Les montants sont donc ramenés à l'échelle des recettes du jeu : ce qu'on
 * veut montrer, ce sont des PROPORTIONS lisibles entre postes et entre gares.
 */
class DepenseFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    /** Profondeur d'historique, alignée sur celle des voyages clôturés. */
    private const JOURS = 30;

    public function getDependencies(): array
    {
        return [
            ReferentielFixtures::class, // les types de dépense
            GareFixtures::class,
            UserFixtures::class,
            StockFixtures::class,       // les fournisseurs, bénéficiaires de certaines charges
            VoyageFixtures::class,      // le crochet 'voyage' des frais de route
        ];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $this->chargerIra($manager);
        $this->chargerSahel($manager);
    }

    /**
     * IRA : un mois de charges réparties sur trois gares et le siège.
     *
     * Le carburant domine, comme dans la réalité d'un transporteur routier ; les salaires et le
     * loyer sont portés par le SIÈGE (gare nulle), donc invisibles d'un chef de gare.
     */
    private function chargerIra(ObjectManager $manager): void
    {
        $entreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class);
        $identreprise = (int) $entreprise->getId();
        $comptable = $this->getReference(Refs::user(Refs::IRA, 'direction'), User::class);
        $aujourdhui = new DateTimeImmutable('today');

        $gares = ['abidjan', 'bouake', 'korhogo'];

        for ($recul = self::JOURS; $recul >= 0; $recul--) {
            $jour = $aujourdhui->modify("-$recul days");
            $index = self::JOURS - $recul;

            // Carburant : chaque gare fait le plein de ses départs, tous les jours.
            foreach ($gares as $rang => $codeGare) {
                $this->creer(
                    $manager,
                    $identreprise,
                    $comptable,
                    'carburant',
                    Refs::IRA,
                    6000 + $this->alea($index + $rang * 7, 0, 40) * 50,
                    $jour->setTime(7, 30),
                    gare: $codeGare,
                    libelle: 'Gasoil départs du jour',
                    beneficiaire: 'Station service',
                );
            }

            // Péage : trois jours sur quatre, au départ d'Abidjan.
            if ($index % 4 !== 0) {
                $this->creer(
                    $manager,
                    $identreprise,
                    $comptable,
                    'peage',
                    Refs::IRA,
                    1500 + $this->alea($index + 3, 0, 8) * 100,
                    $jour->setTime(8, 15),
                    gare: 'abidjan',
                    libelle: 'Péages autoroute du Nord',
                );
            }

            // Entretien courant : une fois par semaine, à Bouaké, chez un fournisseur référencé.
            if ($index % 7 === 3) {
                $this->creer(
                    $manager,
                    $identreprise,
                    $comptable,
                    'entretien',
                    Refs::IRA,
                    8000 + $this->alea($index, 0, 30) * 200,
                    $jour->setTime(11, 0),
                    gare: 'bouake',
                    libelle: 'Vidange et graissage',
                    fournisseur: 'pneus-plus',
                    mode: 'MOBILE_MONEY',
                );
            }

            // Imprévu : deux fois sur le mois, à Korhogo.
            if ($index === 9 || $index === 22) {
                $this->creer(
                    $manager,
                    $identreprise,
                    $comptable,
                    'imprevu',
                    Refs::IRA,
                    5000,
                    $jour->setTime(16, 45),
                    gare: 'korhogo',
                    libelle: 'Dépannage sur la route',
                );
            }
        }

        // -------------------------------------------------------------- Charges du SIÈGE
        // Salaires et loyer : portés par l'entreprise, jamais par un guichet. C'est ce qui rend
        // visible la distinction de portée — un chef de gare ne les voit pas.
        foreach ([1, 0] as $moisRecul) {
            $mois = $aujourdhui->modify("-$moisRecul month")->modify('first day of this month');

            $this->creer(
                $manager, $identreprise, $comptable, 'salaire', Refs::IRA,
                250000, $mois->setTime(9, 0),
                libelle: 'Salaires du personnel', beneficiaire: 'Personnel IRA', mode: 'VIREMENT',
            );
            $this->creer(
                $manager, $identreprise, $comptable, 'loyer', Refs::IRA,
                60000, $mois->setTime(9, 30),
                libelle: 'Loyer du siège', beneficiaire: 'SCI Plateau', mode: 'CHEQUE',
            );
        }

        // -------------------------------------------------- Frais de route (crochet 'voyage')
        /*
            Le forfait remis à l'équipage au départ. Aucun écran ne l'expose encore : ces lignes
            existent pour que le rattachement soit RÉELLEMENT exercé par le jeu de démonstration, et
            que le jour où le résultat d'un voyage se dérivera, il trouve de la matière.
        */
        foreach (['v1' => 6000, 'v3' => 5000] as $codeVoyage => $montant) {
            $voyage = $this->getReference(Refs::voyage(Refs::IRA, $codeVoyage), Voyage::class);
            $this->creer(
                $manager, $identreprise, $comptable, 'fraisroute', Refs::IRA,
                $montant,
                ($voyage->getDatedepartprevue() ?? $aujourdhui)->modify('-1 hour'),
                gare: 'abidjan',
                libelle: 'Ration équipage',
                voyage: $voyage,
            );
        }

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * SAHEL : la compagnie qui perd de l'argent.
     *
     * Deux gares, peu de départs, donc peu de recettes — mais un loyer, des salaires et du carburant
     * qui, eux, tombent quand même. Le bénéfice de l'entreprise doit ressortir NÉGATIF, et au moins
     * une gare en perte : c'est le seul moyen de vérifier à l'œil nu que les écrans passent au rouge.
     */
    private function chargerSahel(ObjectManager $manager): void
    {
        $entreprise = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class);
        $identreprise = (int) $entreprise->getId();
        $admin = $this->getReference(Refs::user(Refs::SAHEL, 'admin'), User::class);
        $aujourdhui = new DateTimeImmutable('today');

        for ($recul = self::JOURS; $recul >= 0; $recul -= 2) {
            $jour = $aujourdhui->modify("-$recul days");
            $index = (int) (self::JOURS - $recul);

            $this->creer(
                $manager, $identreprise, $admin, 'carburant', Refs::SAHEL,
                5000 + $this->alea($index, 0, 20) * 100,
                $jour->setTime(6, 30),
                gare: 'ferkessedougou',
                libelle: 'Gasoil',
            );
        }

        $this->creer(
            $manager, $identreprise, $admin, 'pneumatique', Refs::SAHEL,
            95000, $aujourdhui->modify('-12 days')->setTime(10, 0),
            gare: 'ferkessedougou',
            libelle: 'Jeu de pneus complet',
            fournisseur: 'ferke-auto',
            mode: 'VIREMENT',
        );

        $this->creer(
            $manager, $identreprise, $admin, 'salaire', Refs::SAHEL,
            120000, $aujourdhui->modify('first day of this month')->setTime(9, 0),
            libelle: 'Salaires du personnel', mode: 'VIREMENT',
        );

        $this->creer(
            $manager, $identreprise, $admin, 'loyer', Refs::SAHEL,
            40000, $aujourdhui->modify('first day of this month')->setTime(9, 30),
            libelle: 'Loyer', mode: 'ESPECES',
        );

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    private function creer(
        ObjectManager $manager,
        int $identreprise,
        User $auteur,
        string $codeType,
        string $cie,
        int $montant,
        DateTimeImmutable $date,
        ?string $gare = null,
        ?string $libelle = null,
        ?string $beneficiaire = null,
        ?string $fournisseur = null,
        ?Voyage $voyage = null,
        string $mode = 'ESPECES',
    ): void {
        $depense = (new Depense())
            ->setDatedepense($date)
            ->setMontant($montant)
            ->setTypedepense($this->getReference(Refs::referentiel('typedepense', $cie, $codeType), Typedepense::class))
            // null = le SIÈGE : c'est le discriminant de portée, il n'y a rien d'autre à poser.
            ->setGare($gare === null ? null : $this->getReference(Refs::gare($cie, $gare), Gare::class))
            ->setVoyage($voyage)
            ->setModereglement($mode)
            ->setLibelle($libelle)
            ->setBeneficiaire($beneficiaire)
            ->setIdentreprise($identreprise);

        if ($fournisseur !== null) {
            $depense->setFournisseur($this->getReference(Refs::fournisseur($cie, $fournisseur), Fournisseur::class));
        }

        $depense->setCreatedBy($auteur->getId());

        // Une charge se saisit le jour où elle est engagée : date de création = date de la dépense.
        $manager->persist($this->daterA($depense, $date));
    }

    /**
     * Pseudo-aléa REPRODUCTIBLE : deux chargements donnent les mêmes montants, donc un écart de
     * calcul se reproduit. Même procédé que 'HistoriqueFixtures'.
     */
    private function alea(int $graine, int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + (int) (crc32('itransport-depense-' . $graine) % ($max - $min + 1));
    }
}

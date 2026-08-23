<?php

namespace App\DataFixtures;

use App\Domain\Enum\ApprovisionnementStatus;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Entity\Approvisionnement;
use App\Entity\Detailapprovisionnement;
use App\Entity\Entreprise;
use App\Entity\Fournisseur;
use App\Entity\Marquepiece;
use App\Entity\Model;
use App\Entity\Piece;
use App\Entity\Typepiece;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fournisseurs, pièces détachées, approvisionnements et registre des mouvements.
 *
 * INVARIANT CENTRAL : 'Piece::stockinitial' est le stock COURANT, et il doit valoir exactement la
 * somme algébrique des mouvements 'Inventaire'. Un jeu de données où les deux divergent donne des
 * alertes de stock incohérentes et un inventaire qui ne se recoupe pas — le genre de faux positif
 * qui fait perdre confiance dans le module. Chaque écriture passe donc ici par 'enregistrerMouvement()',
 * copie fidèle de 'StockmouvementService::createMovement()'.
 *
 * DEUX SUBTILITÉS reprises telles quelles du code de production :
 *  - un AJUSTEMENT n'est PAS un 'typemouvement' : le mouvement reste ENTREE ou SORTIE selon le signe,
 *    c'est le 'reference_type' qui vaut 'AJUSTEMENT' (cf. 'AjustementstockProcessor') ;
 *  - un approvisionnement ANNULÉ conserve son ENTREE d'origine et reçoit une SORTIE compensatoire
 *    (cf. 'AnnulerApprovisionnementProcessor') : le solde est nul, mais les deux lignes restent au
 *    registre.
 *
 * Les stocks sont calibrés pour que 'DepannageFixtures', qui consomme ensuite des pièces, laisse
 * une situation de STOCK FAIBLE (plaquettes) et une RUPTURE (batteries) — matière à alertes.
 */
class StockFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;
    use MouvementStockTrait;

    /** code => [libellé, type, marque, modèle, prix unitaire, seuil d'alerte] */
    private const PIECES = [
        'filtre-huile' => ['Filtre à huile', 'filtration', 'mann', 'origine', 12000, 10],
        'filtre-air' => ['Filtre à air', 'filtration', 'mann', 'standard', 18000, 5],
        'plaquette' => ['Plaquette de frein', 'freinage', 'bosch', 'renforce', 45000, 10],
        'pneu' => ['Pneu 315/80 R22.5', 'pneumatique', 'michelin', 'standard', 145000, 6],
        'batterie' => ['Batterie 12V 150Ah', 'electricite', 'varta', 'standard', 85000, 2],
        'ampoule' => ['Ampoule phare H7', 'electricite', 'bosch', 'standard', 3500, 20],
    ];

    public function getDependencies(): array
    {
        return [ReferentielFixtures::class, UserFixtures::class];
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

    private function chargerIra(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $magasinier = $this->getReference(Refs::user(Refs::IRA, 'magasin'), User::class);
        $jour = new DateTimeImmutable('today');

        // ------------------------------------------------------------------------ Fournisseurs
        $fournisseurs = [
            'sotra-pieces' => ['SOTRA Pièces', '+225 27 21 75 40 01', 'contact@sotrapieces.ci', 'Zone industrielle de Yopougon', 'Côte d\'Ivoire'],
            'mann-ci' => ['Mann Filter CI', '+225 27 21 75 40 02', 'ventes@mannfilter.ci', 'Boulevard VGE, Abidjan', 'Côte d\'Ivoire'],
            'pneus-plus' => ['Pneus Plus', '+225 27 31 63 40 03', 'commercial@pneusplus.ci', 'Quartier Air France, Bouaké', 'Côte d\'Ivoire'],
        ];
        foreach ($fournisseurs as $code => [$libelle, $contact, $email, $adresse, $pays]) {
            $fournisseur = (new Fournisseur())
                ->setLibelle($libelle)
                ->setContact($contact)
                ->setEmail($email)
                ->setAdresse($adresse)
                ->setPays($pays)
                ->setIdentreprise($identreprise);
            $manager->persist($fournisseur);
            $this->addReference(Refs::fournisseur(Refs::IRA, $code), $fournisseur);
        }

        // ------------------------------------------------------------------------------ Pièces
        // Stock à ZÉRO au départ : il ne montera que par des mouvements, comme en production.
        foreach (self::PIECES as $code => [$libelle, $type, $marque, $modele, $prix, $seuil]) {
            $piece = (new Piece())
                ->setLibelle($libelle)
                ->setTypepiece($this->getReference(Refs::referentiel('typepiece', Refs::IRA, $type), Typepiece::class))
                ->setMarquepiece($this->getReference(Refs::referentiel('marquepiece', Refs::IRA, $marque), Marquepiece::class))
                ->setModel($this->getReference(Refs::referentiel('model', Refs::IRA, $modele), Model::class))
                ->setPrixunitaire($prix)
                ->setSeuilstock($seuil)
                ->setStockinitial(0)
                ->setIdentreprise($identreprise);
            $manager->persist($piece);
            $this->addReference(Refs::piece(Refs::IRA, $code), $piece);
        }

        // ------------------------------------------------------------------- Approvisionnements
        $a1 = $this->creerApprovisionnement($manager, Refs::IRA, $identreprise, 'sotra-pieces',
            $jour->modify('-20 days')->setTime(9, 30), [
                ['filtre-huile', 40, 11500],
                ['plaquette', 20, 43000],
                ['filtre-air', 30, 17000],
            ]);

        $a2 = $this->creerApprovisionnement($manager, Refs::IRA, $identreprise, 'pneus-plus',
            $jour->modify('-8 days')->setTime(11, 15), [
                ['pneu', 24, 138000],
                ['batterie', 6, 82000],
            ]);

        $a3 = $this->creerApprovisionnement($manager, Refs::IRA, $identreprise, 'mann-ci',
            $jour->modify('-5 days')->setTime(14, 0), [
                ['ampoule', 50, 3200],
            ]);

        // Livraison non conforme, annulée le lendemain : ENTREE puis SORTIE compensatoire.
        $a4 = $this->creerApprovisionnement($manager, Refs::IRA, $identreprise, 'mann-ci',
            $jour->modify('-3 days')->setTime(10, 0), [
                ['filtre-huile', 10, 11800],
            ], ApprovisionnementStatus::ANNULE);

        // Les mouvements portent 'referenceid' = id de l'approvisionnement : il faut les identifiants.
        $manager->flush();

        foreach ([$a1, $a2, $a3, $a4] as $appro) {
            foreach ($appro->getDetailapprovisionnements() as $detail) {
                $this->enregistrerMouvement(
                    $manager,
                    $detail->getPiece(),
                    Typemouvement::ENTREE,
                    $detail->getQuantite(),
                    Referencetype::APPROVISIONNEMENT,
                    $appro->getId(),
                    $identreprise,
                    $magasinier,
                    $appro->getDateappro()
                );
            }
        }

        // Annulation d'A4 : le stock entré est retiré, la ligne d'origine reste au registre.
        foreach ($a4->getDetailapprovisionnements() as $detail) {
            $this->enregistrerMouvement(
                $manager,
                $detail->getPiece(),
                Typemouvement::SORTIE,
                $detail->getQuantite(),
                Referencetype::APPROVISIONNEMENT,
                $a4->getId(),
                $identreprise,
                $magasinier,
                $jour->modify('-2 days')->setTime(8, 45)
            );
        }

        // Ajustement manuel : trois filtres à air cassés au magasin. Mouvement SORTIE, référence AJUSTEMENT.
        $this->enregistrerMouvement(
            $manager,
            $this->getReference(Refs::piece(Refs::IRA, 'filtre-air'), Piece::class),
            Typemouvement::SORTIE,
            3,
            Referencetype::AJUSTEMENT,
            null,
            $identreprise,
            $magasinier,
            $jour->modify('-2 days')->setTime(16, 20)
        );

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    private function chargerSahel(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class)->getId();
        $admin = $this->getReference(Refs::user(Refs::SAHEL, 'admin'), User::class);
        $jour = new DateTimeImmutable('today');

        $fournisseur = (new Fournisseur())
            ->setLibelle('Ferké Auto Pièces')
            ->setContact('+225 27 36 88 55 01')
            ->setEmail('contact@ferkeauto.ci')
            ->setAdresse('Route de Korhogo, Ferkessédougou')
            ->setPays('Côte d\'Ivoire')
            ->setIdentreprise($identreprise);
        $manager->persist($fournisseur);
        $this->addReference(Refs::fournisseur(Refs::SAHEL, 'ferke-auto'), $fournisseur);

        $piece = (new Piece())
            ->setLibelle('Filtre à huile')
            ->setTypepiece($this->getReference(Refs::referentiel('typepiece', Refs::SAHEL, 'filtration'), Typepiece::class))
            ->setMarquepiece($this->getReference(Refs::referentiel('marquepiece', Refs::SAHEL, 'mann'), Marquepiece::class))
            ->setModel($this->getReference(Refs::referentiel('model', Refs::SAHEL, 'origine'), Model::class))
            ->setPrixunitaire(11000)
            ->setSeuilstock(4)
            ->setStockinitial(0)
            ->setIdentreprise($identreprise);
        $manager->persist($piece);
        $this->addReference(Refs::piece(Refs::SAHEL, 'filtre-huile'), $piece);

        $appro = $this->creerApprovisionnement($manager, Refs::SAHEL, $identreprise, 'ferke-auto',
            $jour->modify('-10 days')->setTime(10, 0), [
                ['filtre-huile', 12, 10500],
            ]);

        $manager->flush();

        $this->enregistrerMouvement($manager, $piece, Typemouvement::ENTREE, 12,
            Referencetype::APPROVISIONNEMENT, $appro->getId(), $identreprise, $admin, $appro->getDateappro());

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * @param list<array{0: string, 1: int, 2: int}> $lignes [code de pièce, quantité, prix unitaire]
     */
    private function creerApprovisionnement(
        ObjectManager $manager,
        string $cie,
        int $identreprise,
        string $codeFournisseur,
        DateTimeImmutable $date,
        array $lignes,
        ApprovisionnementStatus $statut = ApprovisionnementStatus::VALIDE
    ): Approvisionnement {
        $appro = (new Approvisionnement())
            ->setDateappro($date)
            ->setFournisseur($this->getReference(Refs::fournisseur($cie, $codeFournisseur), Fournisseur::class))
            ->setStatut($statut->value)
            ->setIdentreprise($identreprise);

        foreach ($lignes as [$codePiece, $quantite, $prixUnitaire]) {
            $detail = (new Detailapprovisionnement())
                ->setPiece($this->getReference(Refs::piece($cie, $codePiece), Piece::class))
                ->setQuantite($quantite)
                ->setPrixunitaire($prixUnitaire)
                ->setCouttotal($quantite * $prixUnitaire)
                ->setApprovisionnement($appro);
            $manager->persist($detail);
            $appro->addDetailapprovisionnement($detail);
        }

        $manager->persist($this->daterA($appro, $date));

        return $appro;
    }
}

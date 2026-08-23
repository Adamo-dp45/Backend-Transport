<?php

namespace App\DataFixtures;

use App\Domain\Enum\DepannageStatus;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Entity\Car;
use App\Entity\Depannage;
use App\Entity\Detaildepannage;
use App\Entity\Detailpersonnel;
use App\Entity\Entreprise;
use App\Entity\Personnel;
use App\Entity\Piece;
use App\Entity\Typepanne;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les dépannages : le point de jonction entre la FLOTTE et le STOCK.
 *
 * Un dépannage consomme des pièces, donc génère des mouvements de SORTIE et fait baisser le stock.
 * Les quantités sont calibrées avec 'StockFixtures' pour aboutir à deux situations qui doivent
 * déclencher des alertes au prochain balayage :
 *   - plaquettes de frein : 8 en stock pour un seuil de 10 → STOCK_FAIBLE ;
 *   - batteries : 0 en stock → STOCK_RUPTURE.
 *
 * Le dépannage D2 est OUVERT DEPUIS SIX JOURS sur un car en panne : c'est la situation que guette
 * 'DEPANNAGE_OUVERT_PROLONGE'. D3 est annulé, ce qui RESTITUE le stock (mouvement ENTREE inverse)
 * tout en restant visible pour l'audit.
 */
class DepannageFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;
    use MouvementStockTrait;

    public function getDependencies(): array
    {
        return [StockFixtures::class, FlotteFixtures::class, PersonnelFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $responsable = $this->getReference(Refs::user(Refs::IRA, 'flotte'), User::class);
        $jour = new DateTimeImmutable('today');

        // ------------------------------------------------ D1 · entretien clôturé (il y a 6 jours)
        $d1 = $this->creerDepannage($manager, $identreprise, 'd1', 'car1', 'freinage',
            'Atelier de la gare d\'Adjamé',
            'Remplacement des plaquettes avant et arrière, vidange moteur et batterie de servitude.',
            DepannageStatus::CLOTURE, $jour->modify('-6 days')->setTime(8, 0), $responsable,
            ['mecanicien1' => 'Intervention mécanique'],
            [
                ['plaquette', 8, 45000],
                ['filtre-huile', 2, 12000],
                ['batterie', 2, 85000],
            ]);

        // ------------------------------ D2 · immobilisation EN COURS depuis 6 jours (car en panne)
        $d2 = $this->creerDepannage($manager, $identreprise, 'd2', 'car3', 'moteur',
            'Bord de route, axe Bouaké — Katiola',
            'Casse moteur en ligne. Véhicule remorqué, remise en état en cours.',
            DepannageStatus::EN_COURS, $jour->modify('-6 days')->setTime(14, 30), $responsable,
            ['mecanicien1' => 'Diagnostic moteur', 'mecanicien2' => 'Remise en état'],
            [
                ['batterie', 4, 85000],
                ['pneu', 6, 145000],
                ['plaquette', 4, 45000],
            ]);

        // ------------------------------------------- D3 · ouvert par erreur puis annulé (stock rendu)
        $d3 = $this->creerDepannage($manager, $identreprise, 'd3', 'car5', 'electrique',
            'Atelier de la gare d\'Adjamé',
            'Intervention ouverte sur le mauvais véhicule : annulée, pièces remises en stock.',
            DepannageStatus::ANNULE, $jour->modify('-2 days')->setTime(9, 15), $responsable,
            [],
            [
                ['ampoule', 4, 3500],
            ]);

        // Les mouvements portent 'referenceid' = id du dépannage : il faut les identifiants.
        $manager->flush();

        foreach ([$d1, $d2, $d3] as $depannage) {
            foreach ($depannage->getDetaildepannages() as $detail) {
                $this->enregistrerMouvement(
                    $manager,
                    $detail->getPiece(),
                    Typemouvement::SORTIE,
                    $detail->getQuantite(),
                    Referencetype::DEPANNAGE,
                    $depannage->getId(),
                    $identreprise,
                    $responsable,
                    $depannage->getDatedepannage()
                );
            }
        }

        // Annulation de D3 : les pièces retournent en stock, la sortie d'origine reste au registre.
        foreach ($d3->getDetaildepannages() as $detail) {
            $this->enregistrerMouvement(
                $manager,
                $detail->getPiece(),
                Typemouvement::ENTREE,
                $detail->getQuantite(),
                Referencetype::DEPANNAGE,
                $d3->getId(),
                $identreprise,
                $responsable,
                $jour->modify('-2 days')->setTime(11, 0)
            );
        }

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * @param array<string, string>                  $equipe code de personnel => motif
     * @param list<array{0:string,1:int,2:int}>      $pieces [code de pièce, quantité, prix unitaire]
     */
    private function creerDepannage(
        ObjectManager $manager,
        int $identreprise,
        string $code,
        string $codeCar,
        string $codeTypepanne,
        string $lieu,
        string $description,
        DepannageStatus $statut,
        DateTimeImmutable $date,
        User $auteur,
        array $equipe,
        array $pieces
    ): Depannage {
        $depannage = new Depannage();
        $depannage
            ->setCar($this->getReference(Refs::car(Refs::IRA, $codeCar), Car::class))
            ->setTypepanne($this->getReference(Refs::referentiel('typepanne', Refs::IRA, $codeTypepanne), Typepanne::class))
            ->setLieudepannage($lieu)
            ->setDescription($description)
            ->setDatedepannage($date)
            ->setStatut($statut->value)
            ->setIdentreprise($identreprise);
        $depannage->setCreatedBy($auteur->getId());

        $cout = 0;
        foreach ($pieces as [$codePiece, $quantite, $prixUnitaire]) {
            $detail = (new Detaildepannage())
                ->setDepannage($depannage)
                ->setPiece($this->getReference(Refs::piece(Refs::IRA, $codePiece), Piece::class))
                ->setQuantite($quantite)
                // Prix figé au moment de l'intervention : celui de la pièce peut changer ensuite.
                ->setPrixunitaire($prixUnitaire);
            $manager->persist($detail);
            $depannage->addDetaildepannage($detail);
            $cout += $quantite * $prixUnitaire;
        }
        $depannage->setCouttotal($cout);

        // L'affectation d'un mécanicien passe par 'Detailpersonnel', comme pour un voyage.
        foreach ($equipe as $codePersonnel => $motif) {
            $detailPersonnel = (new Detailpersonnel())
                ->setPersonnel($this->getReference(Refs::personnel(Refs::IRA, $codePersonnel), Personnel::class))
                ->setDepannage($depannage)
                ->setMotif($motif);
            $detailPersonnel->setIdentreprise($identreprise);
            $manager->persist($detailPersonnel);
        }

        $manager->persist($this->daterA($depannage, $date));
        $this->addReference('depannage-' . Refs::IRA . '-' . $code, $depannage);

        return $depannage;
    }
}

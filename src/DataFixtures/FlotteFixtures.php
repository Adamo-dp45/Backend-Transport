<?php

namespace App\DataFixtures;

use App\Domain\Enum\CarStatus;
use App\Entity\Car;
use App\Entity\Entreprise;
use App\Entity\Marque;
use App\Entity\Modelvehicule;
use App\Entity\Siege;
use App\Entity\Typevehicule;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les véhicules ET leurs sièges.
 *
 * Le siège appartient au CAR, jamais au voyage : c'est lui que vise un billet, et c'est pourquoi un
 * changement de véhicule oblige à réasseoir les passagers. Les sièges doivent donc exister dès la
 * création du car — un car sans sièges rend le plan vide et toute vente impossible.
 *
 * La génération reproduit fidèlement 'CarProcessor::synchroniserSieges()' : on matérialise une
 * GRILLE explicite ('plansieges'), puis un siège par cellule non vide, avec sa rangée, sa colonne
 * ABSOLUE (l'allée compte comme une colonne) et le côté 'GRILLE'. Diverger ici produirait des plans
 * incohérents dès la première modification de capacité via l'API.
 */
class FlotteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** code => [matricule, nb sièges, gauche, droite, marque, modèle, type, statut, année] */
    private const CARS = [
        Refs::IRA => [
            'car1' => ['4521 AB 01', 60, 2, 2, 'mercedes', 'tourismo', 'autocar', CarStatus::EN_VOYAGE, '2021-04-12'],
            'car2' => ['7834 CD 01', 70, 2, 3, 'yutong', 'zk6119', 'vip', CarStatus::EN_VOYAGE, '2022-08-30'],
            'car3' => ['1290 EF 01', 30, 2, 2, 'toyota', 'coaster', 'minibus', CarStatus::EN_PANNE, '2020-01-20'],
            'car4' => ['6677 GH 01', 60, 2, 2, 'higer', 'klq6122', 'autocar', CarStatus::EN_VOYAGE, '2023-02-08'],
            'car5' => ['2211 IJ 01', 50, 2, 2, 'mercedes', 'tourismo', 'autocar', CarStatus::DISPONIBLE, '2023-11-15'],
        ],
        Refs::SAHEL => [
            'car1' => ['0101 SV 01', 40, 2, 2, 'toyota', 'coaster', 'minibus', CarStatus::DISPONIBLE, '2023-05-05'],
        ],
    ];

    public function getDependencies(): array
    {
        return [ReferentielFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::CARS as $cie => $cars) {
            $identreprise = $this->getReference(Refs::entreprise($cie), Entreprise::class)->getId();

            foreach ($cars as $code => [$matricule, $nbr, $gauche, $droite, $marque, $modele, $type, $statut, $annee]) {
                $car = new Car();
                $car
                    ->setMatricule($matricule)
                    ->setNbrsiege($nbr)
                    ->setSiegesGauche($gauche)
                    ->setSiegesDroite($droite)
                    ->setEtat($statut->value)
                    ->setDatearrivee(new DateTimeImmutable($annee))
                    ->setMarque($this->getReference(Refs::referentiel('marque', $cie, $marque), Marque::class))
                    ->setModelvehicule($this->getReference(Refs::referentiel('modelvehicule', $cie, $modele), Modelvehicule::class))
                    ->setTypevehicule($this->getReference(Refs::referentiel('typevehicule', $cie, $type), Typevehicule::class))
                    ->setIdentreprise($identreprise);

                $manager->persist($car);
                $this->addReference(Refs::car($cie, $code), $car);

                $this->creerSieges($manager, $car, $cie, $code, $identreprise);
            }
        }

        $manager->flush();
    }

    /**
     * Matérialise la grille standard (gauche | allée | droite) puis crée un siège par cellule.
     * Copie conforme de 'CarProcessor::grilleStandard()' + 'calculerPlanDepuisMap()'.
     */
    private function creerSieges(ObjectManager $manager, Car $car, string $cie, string $codeCar, int $identreprise): void
    {
        $nbr = $car->getNbrsiege() ?? 0;
        $gauche = $car->getSiegesGauche() ?? 0;
        $droite = $car->getSiegesDroite() ?? 0;

        $grille = [];
        $numero = 1;
        $allee = $gauche > 0 && $droite > 0;
        while ($numero <= $nbr) {
            $rangee = [];
            for ($c = 1; $c <= $gauche && $numero <= $nbr; $c++) {
                $rangee[] = $numero++;
            }
            if ($allee) {
                $rangee[] = null; // allée centrale : cellule vide, mais elle occupe une colonne
            }
            for ($c = 1; $c <= $droite && $numero <= $nbr; $c++) {
                $rangee[] = $numero++;
            }
            $grille[] = $rangee;
        }
        // La grille devient la SOURCE UNIQUE du plan, éditable ensuite depuis le formulaire.
        $car->setPlansieges($grille);

        foreach ($grille as $indexRangee => $cellules) {
            $colonne = 0;
            foreach ($cellules as $cellule) {
                $colonne++;
                $num = (int) $cellule;
                if ($num <= 0) {
                    continue; // allée / trou
                }
                $siege = (new Siege())
                    ->setNumero($num)
                    ->setRangee($indexRangee + 1)
                    ->setColonne($colonne)
                    ->setCote('GRILLE')
                    ->setCar($car)
                    ->setIdentreprise($identreprise);
                $manager->persist($siege);
                $this->addReference(Refs::siege($cie, $codeCar, $num), $siege);
            }
        }
    }
}

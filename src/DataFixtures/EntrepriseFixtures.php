<?php

namespace App\DataFixtures;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\ConfigRecette;
use App\Entity\ConfigRemise;
use App\Entity\Entreprise;
use App\Entity\Maintenance;
use App\Entity\ParametreReservation;
use App\Entity\ProgrammeFidelite;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les compagnies et leur PARAMÉTRAGE (remise, recette, réservation, fidélité), plus le singleton
 * de maintenance de la plateforme.
 *
 * DEUX compagnies, dont une volontairement pauvre : c'est le seul moyen de vérifier que
 * 'EntrepriseScopeExtension' isole réellement les données. Un jeu mono-entreprise donne l'illusion
 * que tout fonctionne alors qu'un filtre manquant ne se voit pas.
 */
class EntrepriseFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        // ------------------------------------------------------------------ Compagnie principale
        $ira = new Entreprise();
        $ira
            ->setLibelle('IRA Transport')
            ->setSigle('IRA')
            // Le slug est l'identifiant PUBLIC consommé par les apps mobiles ('?slug=') : il doit
            // correspondre à ce que 'SlugService' produirait à partir du libellé.
            ->setSlug('ira-transport')
            ->setContact1('+225 27 20 30 40 50')
            ->setContact2('+225 07 08 09 10 11')
            ->setAdresse('Gare routière d\'Adjamé, Abidjan')
            ->setEmail('contact@ira-transport.ci')
            ->setSiteweb('https://ira-transport.ci')
            ->setRccm('CI-ABJ-2019-B-12345')
            ->setBanque('SGBCI')
            ->setType('SARL')
            ->setCentreimpot('Abidjan Plateau')
            ->setTauxtva(18)
            ->setAnneecreation(new DateTimeImmutable('2019-03-15'))
            ->setStatut(ReferenceStatus::ACTIF->value);
        $manager->persist($ira);

        // ------------------------------------------------------- Seconde compagnie (isolation)
        $sahel = new Entreprise();
        $sahel
            ->setLibelle('Sahel Voyages')
            ->setSigle('SVO')
            ->setSlug('sahel-voyages')
            ->setContact1('+225 27 36 12 34 56')
            ->setAdresse('Gare de Ferkessédougou')
            ->setEmail('contact@sahel-voyages.ci')
            ->setType('SARL')
            ->setTauxtva(18)
            ->setAnneecreation(new DateTimeImmutable('2022-09-01'))
            ->setStatut(ReferenceStatus::ACTIF->value);
        $manager->persist($sahel);

        // Les paramétrages portent 'identreprise' (un int, pas une relation) : il faut les identifiants.
        $manager->flush();

        $this->addReference(Refs::entreprise(Refs::IRA), $ira);
        $this->addReference(Refs::entreprise(Refs::SAHEL), $sahel);

        foreach ([$ira, $sahel] as $entreprise) {
            $id = $entreprise->getId();

            // Plafond de remise accordable par un agent (garde-fou anti-abus à la vente).
            $manager->persist((new ConfigRemise())->setMaxpourcentage(20)->setIdentreprise($id));

            // Composition du chiffre d'affaires : IRA compte les courriers, Sahel les exclut.
            $manager->persist(
                (new ConfigRecette())
                    ->setCourriershorsca($entreprise === $sahel)
                    ->setIdentreprise($id)
            );
        }

        // ----------------------------------------------------------- Paramètres de réservation
        // Deux délais DISTINCTS : présentation (avant le passage du car) et paiement (depuis la
        // création). IRA laisse 30 min pour payer et ferme le guichet 15 min avant le passage.
        $manager->persist(
            (new ParametreReservation())
                ->setDelaiPresentationMinutes(15)
                ->setDelaiPaiementMinutes(30)
                ->setPenaliteType('POURCENTAGE')
                ->setPenaliteValeur(25)
                ->setFenetreRegularisationJours(7)
                ->setIdentreprise($ira->getId())
        );
        $manager->persist(
            (new ParametreReservation())
                ->setDelaiPresentationMinutes(30)
                ->setDelaiPaiementMinutes(60)
                ->setPenaliteType('AUCUNE')
                ->setPenaliteValeur(0)
                ->setFenetreRegularisationJours(3)
                ->setIdentreprise($sahel->getId())
        );

        // Carte à tampons : active chez IRA seulement (l'état est dérivé des billets, pas stocké).
        $manager->persist(
            (new ProgrammeFidelite())
                ->setSeuil(10)
                ->setRecompensePourcentage(100)
                ->setActif(true)
                ->setIdentreprise($ira->getId())
        );

        // ------------------------------------------------------------------------- Maintenance
        // Singleton GLOBAL (hors périmètre entreprise) : inactif, sinon plus personne n'entre.
        $manager->persist(
            (new Maintenance())
                ->setActif(false)
                ->setMessage('Plateforme en cours de mise à jour. Merci de réessayer dans quelques minutes.')
        );

        $manager->flush();
    }
}

<?php

namespace App\DataFixtures;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Ville;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les gares : l'unité de PÉRIMÈTRE de toute l'application (cf. 'GareScopeExtension').
 *
 * Le réseau d'IRA est construit pour que la ligne principale traverse quatre gares — il faut au
 * moins un arrêt INTERMÉDIAIRE pour que « l'origine prépare · l'intermédiaire réceptionne · le
 * terminus clôture » veuille dire quelque chose, et pour exercer le départ partiel.
 */
class GareFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** code => [libellé, ville, chef de gare, contact, statut] */
    private const GARES = [
        Refs::IRA => [
            'abidjan' => ['Gare d\'Adjamé', 'abidjan', 'Koffi Aristide', '+225 27 20 37 11 22', ReferenceStatus::ACTIF],
            'yamoussoukro' => ['Gare de Yamoussoukro', 'yamoussoukro', 'N\'Guessan Marie', '+225 27 30 64 11 33', ReferenceStatus::ACTIF],
            'bouake' => ['Gare de Bouaké', 'bouake', 'Traoré Souleymane', '+225 27 31 63 11 44', ReferenceStatus::ACTIF],
            'korhogo' => ['Gare de Korhogo', 'korhogo', 'Silué Adama', '+225 27 36 86 11 55', ReferenceStatus::ACTIF],
            'daloa' => ['Gare de Daloa', 'daloa', 'Bamba Fatoumata', '+225 27 32 78 11 66', ReferenceStatus::ACTIF],
            // Gare SUSPENDUE : le listing doit continuer de l'afficher, mais elle ne doit plus servir.
            'san-pedro' => ['Gare de San-Pédro', 'san-pedro', 'Gnamien Paul', '+225 27 34 71 11 77', ReferenceStatus::SUSPENDU],
        ],
        Refs::SAHEL => [
            'ferkessedougou' => ['Gare de Ferkessédougou', 'ferkessedougou', 'Ouattara Ibrahim', '+225 27 36 88 22 11', ReferenceStatus::ACTIF],
            'ouangolodougou' => ['Gare de Ouangolodougou', 'ouangolodougou', 'Coulibaly Salif', '+225 27 36 89 22 22', ReferenceStatus::ACTIF],
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
        foreach (self::GARES as $cie => $gares) {
            $identreprise = $this->getReference(Refs::entreprise($cie), Entreprise::class)->getId();

            foreach ($gares as $code => [$libelle, $codeVille, $chef, $contact, $statut]) {
                $gare = new Gare();
                $gare
                    ->setLibelle($libelle)
                    ->setVille($this->getReference(Refs::ville($cie, $codeVille), Ville::class))
                    ->setChefgare($chef)
                    ->setContact1($contact)
                    ->setDescription(sprintf('Gare de %s — quai bagages, guichet et salle d\'attente.', $libelle))
                    ->setStatut($statut->value)
                    ->setDatecreation(new DateTimeImmutable('2019-06-01'))
                    ->setIdentreprise($identreprise);
                $manager->persist($gare);
                $this->addReference(Refs::gare($cie, $code), $gare);
            }
        }

        $manager->flush();
    }
}

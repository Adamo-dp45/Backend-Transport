<?php

namespace App\DataFixtures;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Entreprise;
use App\Entity\Personnel;
use App\Entity\Typepersonnel;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les employés de chaque compagnie.
 *
 * Il n'existe AUCUNE notion figée de « chauffeur » dans le modèle : 'Typepersonnel' est un libellé
 * libre par entreprise, et les statistiques agrègent par personnel en exposant son type. Le jeu
 * fournit donc plusieurs types réellement distincts, sinon la ventilation par type ('?typepersonnel=')
 * n'a rien à montrer.
 *
 * L'affectation à un voyage ou à un dépannage ne se fait pas ici : elle passe par 'Detailpersonnel'
 * (cf. 'VoyageFixtures' et 'DepannageFixtures').
 */
class PersonnelFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** code => [nom, prénom, contact, type, date d'embauche, statut] */
    private const PERSONNELS = [
        Refs::IRA => [
            'chauffeur1' => ['Kouamé', 'Désiré', '+225 07 11 22 33 44', 'chauffeur', '2019-07-01', ReferenceStatus::ACTIF],
            'chauffeur2' => ['Sangaré', 'Vamara', '+225 07 11 22 33 45', 'chauffeur', '2020-02-17', ReferenceStatus::ACTIF],
            'chauffeur3' => ['Tanoh', 'Serge', '+225 07 11 22 33 46', 'chauffeur', '2021-09-06', ReferenceStatus::ACTIF],
            'chauffeur4' => ['Fofana', 'Mamadou', '+225 07 11 22 33 47', 'chauffeur', '2022-03-14', ReferenceStatus::ACTIF],
            'convoyeur1' => ['Aka', 'Prisca', '+225 05 22 33 44 55', 'convoyeur', '2019-07-01', ReferenceStatus::ACTIF],
            'convoyeur2' => ['Zadi', 'Olivier', '+225 05 22 33 44 56', 'convoyeur', '2020-11-23', ReferenceStatus::ACTIF],
            'convoyeur3' => ['Kone', 'Aminata', '+225 05 22 33 44 57', 'convoyeur', '2023-01-09', ReferenceStatus::ACTIF],
            'mecanicien1' => ['Guei', 'Roland', '+225 01 33 44 55 66', 'mecanicien', '2019-08-19', ReferenceStatus::ACTIF],
            'mecanicien2' => ['Cissé', 'Abou', '+225 01 33 44 55 67', 'mecanicien', '2021-05-03', ReferenceStatus::ACTIF],
            'bagagiste1' => ['Yapi', 'Franck', '+225 01 33 44 55 68', 'bagagiste', '2022-06-20', ReferenceStatus::ACTIF],
            // Employé SUSPENDU : il doit rester visible en historique mais ne plus être affectable.
            'chauffeur5' => ['Bakayoko', 'Issouf', '+225 07 11 22 33 48', 'chauffeur', '2020-04-02', ReferenceStatus::SUSPENDU],
        ],
        Refs::SAHEL => [
            'chauffeur1' => ['Doumbia', 'Yacouba', '+225 07 99 88 77 66', 'chauffeur', '2022-10-01', ReferenceStatus::ACTIF],
            'convoyeur1' => ['Sanogo', 'Rokia', '+225 05 99 88 77 65', 'convoyeur', '2022-10-01', ReferenceStatus::ACTIF],
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
        foreach (self::PERSONNELS as $cie => $personnels) {
            $identreprise = $this->getReference(Refs::entreprise($cie), Entreprise::class)->getId();
            $compteur = 0;

            foreach ($personnels as $code => [$nom, $prenom, $contact, $type, $embauche, $statut]) {
                $personnel = new Personnel();
                $personnel
                    ->setNom($nom)
                    ->setPrenom($prenom)
                    ->setContact($contact)
                    // Matricule interne, au format prévu par 'PersonnelProcessor'.
                    ->setCode(sprintf('PER-%d-%04d', $identreprise, ++$compteur))
                    ->setTypepersonnel($this->getReference(Refs::referentiel('typepersonnel', $cie, $type), Typepersonnel::class))
                    ->setDateembauche(new DateTimeImmutable($embauche))
                    ->setStatut($statut->value)
                    ->setIdentreprise($identreprise);

                $manager->persist($personnel);
                $this->addReference(Refs::personnel($cie, $code), $personnel);
            }
        }

        $manager->flush();
    }
}

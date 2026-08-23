<?php

namespace App\DataFixtures;

use App\Entity\Entreprise;
use App\Entity\Marque;
use App\Entity\Marquepiece;
use App\Entity\Model;
use App\Entity\Modelvehicule;
use App\Entity\Typepanne;
use App\Entity\Typepersonnel;
use App\Entity\Typepiece;
use App\Entity\Typevehicule;
use App\Entity\Ville;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les référentiels de chaque compagnie : villes, types/marques/modèles de véhicule, types de panne,
 * types de personnel, types/marques/modèles de pièce.
 *
 * Tout est DUPLIQUÉ par compagnie (aucun référentiel n'est partagé) : c'est exactement ce
 * qu'impose 'EntrepriseOwnedInterface'. Deux compagnies peuvent donc avoir un « Chauffeur » chacune,
 * sans se voir.
 */
class ReferentielFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** Chaque compagnie a SES villes : ce sont les points du réseau qu'elle dessert. */
    private const VILLES = [
        Refs::IRA => [
            'abidjan' => 'Abidjan',
            'yamoussoukro' => 'Yamoussoukro',
            'bouake' => 'Bouaké',
            'korhogo' => 'Korhogo',
            'daloa' => 'Daloa',
            'san-pedro' => 'San-Pédro',
        ],
        Refs::SAHEL => [
            'ferkessedougou' => 'Ferkessédougou',
            'ouangolodougou' => 'Ouangolodougou',
        ],
    ];

    private const TYPES_VEHICULE = ['autocar' => 'Autocar', 'minibus' => 'Minibus', 'vip' => 'Autocar VIP'];

    private const MARQUES = ['mercedes' => 'Mercedes-Benz', 'toyota' => 'Toyota', 'higer' => 'Higer', 'yutong' => 'Yutong'];

    private const MODELES_VEHICULE = ['tourismo' => 'Tourismo', 'coaster' => 'Coaster', 'klq6122' => 'KLQ 6122', 'zk6119' => 'ZK 6119'];

    private const TYPES_PANNE = [
        'moteur' => 'Panne moteur',
        'pneumatique' => 'Crevaison / pneumatique',
        'electrique' => 'Panne électrique',
        'freinage' => 'Système de freinage',
        'entretien' => 'Entretien périodique',
    ];

    private const TYPES_PERSONNEL = [
        'chauffeur' => 'Chauffeur',
        'convoyeur' => 'Convoyeur',
        'mecanicien' => 'Mécanicien',
        'bagagiste' => 'Bagagiste',
    ];

    private const TYPES_PIECE = ['filtration' => 'Filtration', 'freinage' => 'Freinage', 'pneumatique' => 'Pneumatique', 'electricite' => 'Électricité'];

    private const MARQUES_PIECE = ['bosch' => 'Bosch', 'mann' => 'Mann Filter', 'michelin' => 'Michelin', 'varta' => 'Varta'];

    private const MODELES_PIECE = ['standard' => 'Standard', 'renforce' => 'Renforcé', 'origine' => 'Pièce d\'origine'];

    public function getDependencies(): array
    {
        return [EntrepriseFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach ([Refs::IRA, Refs::SAHEL] as $cie) {
            $entreprise = $this->getReference(Refs::entreprise($cie), Entreprise::class);
            $id = $entreprise->getId();

            foreach (self::VILLES[$cie] as $code => $nom) {
                $ville = (new Ville())->setNom($nom)->setIdentreprise($id);
                $manager->persist($ville);
                $this->addReference(Refs::ville($cie, $code), $ville);
            }

            $this->creerLibelles($manager, Typevehicule::class, 'typevehicule', $cie, $id, self::TYPES_VEHICULE);
            $this->creerLibelles($manager, Marque::class, 'marque', $cie, $id, self::MARQUES);
            $this->creerLibelles($manager, Modelvehicule::class, 'modelvehicule', $cie, $id, self::MODELES_VEHICULE);
            $this->creerLibelles($manager, Typepanne::class, 'typepanne', $cie, $id, self::TYPES_PANNE);
            $this->creerLibelles($manager, Typepersonnel::class, 'typepersonnel', $cie, $id, self::TYPES_PERSONNEL);
            $this->creerLibelles($manager, Typepiece::class, 'typepiece', $cie, $id, self::TYPES_PIECE);
            $this->creerLibelles($manager, Marquepiece::class, 'marquepiece', $cie, $id, self::MARQUES_PIECE);
            $this->creerLibelles($manager, Model::class, 'model', $cie, $id, self::MODELES_PIECE);
        }

        $manager->flush();
    }

    /**
     * Les référentiels de l'application partagent tous la même forme : un libellé + 'identreprise'.
     * Une seule fabrique évite huit boucles identiques.
     *
     * @param class-string        $classe
     * @param array<string,string> $libelles code de référence => libellé affiché
     */
    private function creerLibelles(
        ObjectManager $manager,
        string $classe,
        string $type,
        string $cie,
        int $identreprise,
        array $libelles
    ): void {
        foreach ($libelles as $code => $libelle) {
            $entity = new $classe();
            $entity->setLibelle($libelle)->setIdentreprise($identreprise);
            $manager->persist($entity);
            $this->addReference(Refs::referentiel($type, $cie, $code), $entity);
        }
    }
}

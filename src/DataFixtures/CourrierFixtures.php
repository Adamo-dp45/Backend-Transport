<?php

namespace App\DataFixtures;

use App\Domain\Enum\CourrierStatus;
use App\Domain\Enum\DetailcourrierStatus;
use App\Entity\Courrier;
use App\Entity\Detailcourrier;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Tarifcourrier;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Grille de taxation des colis, courriers et détails de colis.
 *
 * La taxe se calcule PAR COLIS, par tranche de VALEUR déclarée, et le montant du courrier est la
 * somme des taxes de ses colis (les frais de suivi sont à part). Les fixtures recalculent cette
 * somme au lieu de la saisir : un total qui ne correspond pas au détail rendrait tout contrôle de
 * caisse impossible à vérifier.
 *
 * Les statuts suivent le voyage porteur : EN_ATTENTE (pas encore embarqué) → EN_TRANSIT →
 * RECEPTIONNE (arrivé en gare) → LIVRE (remis au destinataire). Le jeu en couvre la chaîne
 * complète, plus une annulation et un colis PERDU (le courrier reste, seul le détail est perdu).
 */
class CourrierFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    private int $compteur = 0;

    public function getDependencies(): array
    {
        return [VoyageFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $agentAbidjan = $this->getReference(Refs::user(Refs::IRA, 'agent-abidjan'), User::class);
        $agentYamoussoukro = $this->getReference(Refs::user(Refs::IRA, 'agent-yamoussoukro'), User::class);

        $hier = new DateTimeImmutable('yesterday');
        $v1 = $this->getReference(Refs::voyage(Refs::IRA, 'v1'), Voyage::class);
        $v2 = $this->getReference(Refs::voyage(Refs::IRA, 'v2'), Voyage::class);

        // ------------------------------------------------------- Grille de taxation par valeur
        // La DERNIÈRE tranche est ouverte ('valeurmax' à null) : sans elle, un colis de grande
        // valeur ne trouverait aucun tarif et la création échouerait.
        $tranches = [
            'tranche1' => ['Colis ordinaire (jusqu\'à 50 000 F)', 0, 50000, 1000],
            'tranche2' => ['Colis de valeur (50 001 à 200 000 F)', 50001, 200000, 3000],
            'tranche3' => ['Colis de grande valeur (plus de 200 000 F)', 200001, null, 7500],
        ];
        foreach ($tranches as $code => [$libelle, $min, $max, $taxe]) {
            $tarif = (new Tarifcourrier())
                ->setLibelle($libelle)
                ->setValeurmin($min)
                ->setValeurmax($max)
                ->setMontanttaxe($taxe)
                ->setIdentreprise($identreprise);
            $manager->persist($tarif);
            $this->addReference(Refs::tarifcourrier(Refs::IRA, $code), $tarif);
        }

        // ------------------------------------------- Acheminé hier et déjà remis au destinataire
        $this->creerCourrier($manager, $identreprise, 'k1', 'abidjan', 'korhogo', $v1,
            'Kouassi Bernard', '+225 07 12 12 12 01', 'Ouattara Salimata', '+225 07 12 12 12 02',
            CourrierStatus::STATUT_LIVRE, $agentAbidjan, $hier->setTime(6, 40),
            datelivraison: $hier->setTime(17, 20),
            fraissuivi: 500,
            colis: [
                ['Documents', 'Dossier administratif scellé', 'Enveloppe kraft', 'NORMAL', 1, 20000, 'tranche1'],
                ['Marchandise', 'Lot de pagnes', 'Carton', 'NORMAL', 12, 120000, 'tranche2'],
            ]);

        // ------------------------------------------- Arrivé en gare, en attente de récupération
        $this->creerCourrier($manager, $identreprise, 'k2', 'abidjan', 'bouake', $v1,
            'Société IVOTECH', '+225 27 20 33 44 55', 'Bamba Ismaël', '+225 07 12 12 12 03',
            CourrierStatus::STATUT_RECEPTIONNE, $agentAbidjan, $hier->setTime(6, 50),
            colis: [
                ['Électronique', 'Onduleur 650 VA', 'Carton d\'origine', 'FRAGILE', 6, 85000, 'tranche2'],
            ]);

        // --------------------------------------------------------------- En route en ce moment
        $this->creerCourrier($manager, $identreprise, 'k3', 'abidjan', 'korhogo', $v2,
            'Pharmacie du Plateau', '+225 27 20 21 22 23', 'Clinique Sainte-Marie', '+225 27 36 86 11 99',
            CourrierStatus::STATUT_EN_TRANSIT, $agentAbidjan, $v2->getDatedepartprevue()->modify('-35 minutes'),
            fraissuivi: 1000,
            colis: [
                ['Médicaments', 'Colis pharmaceutique réfrigéré', 'Glacière', 'FRAGILE', 8, 350000, 'tranche3'],
            ]);

        // ------------------------------- Déposé au guichet, PAS ENCORE affecté à un voyage -----
        $this->creerCourrier($manager, $identreprise, 'k4', 'abidjan', 'daloa', null,
            'Yao Alphonse', '+225 07 12 12 12 04', 'Yao Michel', '+225 07 12 12 12 05',
            CourrierStatus::STATUT_EN_ATTENTE, $agentAbidjan, (new DateTimeImmutable())->modify('-2 hours'),
            colis: [
                ['Documents', 'Actes notariés', 'Enveloppe A4', 'NORMAL', 1, 15000, 'tranche1'],
            ]);

        // ----------------------------------------------------------------- Annulé avant embarquement
        $this->creerCourrier($manager, $identreprise, 'k5', 'abidjan', 'korhogo', null,
            'Diarra Fatou', '+225 07 12 12 12 06', 'Diarra Oumar', '+225 07 12 12 12 07',
            CourrierStatus::STATUT_ANNULE, $agentAbidjan, $hier->setTime(15, 10),
            colis: [
                ['Marchandise', 'Sac de vivres', 'Sac tissé', 'NORMAL', 25, 40000, 'tranche1'],
            ]);

        // ------------------------------------- Livré, mais avec UN colis perdu sur les deux ----
        // Le courrier reste livré : c'est le DÉTAIL qui porte l'incident.
        $this->creerCourrier($manager, $identreprise, 'k6', 'yamoussoukro', 'korhogo', $v1,
            'Coulibaly Awa', '+225 07 12 12 12 08', 'Coulibaly Seydou', '+225 07 12 12 12 09',
            CourrierStatus::STATUT_LIVRE, $agentYamoussoukro, $hier->setTime(9, 50),
            datelivraison: $hier->setTime(17, 45),
            colis: [
                ['Marchandise', 'Colis de vêtements', 'Carton', 'NORMAL', 9, 60000, 'tranche2'],
                ['Marchandise', 'Petit colis non retrouvé à l\'arrivée', 'Sachet', 'NORMAL', 2, 18000, 'tranche1', DetailcourrierStatus::STATUT_PERDU],
            ]);

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * @param list<array{0:string,1:string,2:string,3:string,4:int,5:int,6:string,7?:DetailcourrierStatus}> $colis
     *        [nature, désignation, emballage, type, poids, valeur déclarée, code de tranche, statut]
     */
    private function creerCourrier(
        ObjectManager $manager,
        int $identreprise,
        string $code,
        string $codeGareDepart,
        string $codeGareArrivee,
        ?Voyage $voyage,
        string $nomExpediteur,
        string $contactExpediteur,
        string $nomDestinataire,
        string $contactDestinataire,
        CourrierStatus $statut,
        User $agent,
        DateTimeImmutable $depose,
        array $colis,
        ?DateTimeImmutable $datelivraison = null,
        ?int $fraissuivi = null
    ): void {
        $courrier = new Courrier();
        $courrier
            ->setCodecourrier(sprintf('CRR-%s-%d', $depose->format('Y'), ++$this->compteur))
            ->setNomexpediteur($nomExpediteur)
            ->setContactexpediteur($contactExpediteur)
            ->setNomdestinataire($nomDestinataire)
            ->setContactdestinataire($contactDestinataire)
            // Les gares sont OBLIGATOIRES dès la création ; le voyage, lui, peut être affecté plus tard.
            ->setGaredepart($this->getReference(Refs::gare(Refs::IRA, $codeGareDepart), Gare::class))
            ->setGarearrivee($this->getReference(Refs::gare(Refs::IRA, $codeGareArrivee), Gare::class))
            ->setVoyage($voyage)
            ->setStatut($statut->value)
            ->setFraissuivi($fraissuivi)
            ->setDatelivraison($datelivraison)
            ->setIdentreprise($identreprise);
        $courrier->setCreatedBy($agent->getId());

        $montantTotal = 0;
        foreach ($colis as $ligne) {
            [$nature, $designation, $emballage, $type, $poids, $valeur, $codeTranche] = $ligne;
            $statutColis = $ligne[7] ?? DetailcourrierStatus::STATUT_NORMAL;

            $tarif = $this->getReference(Refs::tarifcourrier(Refs::IRA, $codeTranche), Tarifcourrier::class);
            $taxe = (int) $tarif->getMontanttaxe();

            $detail = (new Detailcourrier())
                ->setCourrier($courrier)
                ->setNature($nature)
                ->setDesignation($designation)
                ->setEmballage($emballage)
                ->setType($type)
                ->setPoids($poids)
                ->setValeur($valeur)
                ->setMontant($taxe)
                ->setTarifcourrier($tarif)
                ->setStatut($statutColis->value);
            $detail->setIdentreprise($identreprise);
            $manager->persist($this->daterA($detail, $depose));
            $courrier->addDetailcourrier($detail);

            $montantTotal += $taxe;
        }

        // Le total est la SOMME des taxes des colis, comme le calcule 'CourrierProcessor'.
        $courrier->setMontant($montantTotal);

        $manager->persist($this->daterA($courrier, $depose));
        $this->addReference('courrier-' . Refs::IRA . '-' . $code, $courrier);
    }
}

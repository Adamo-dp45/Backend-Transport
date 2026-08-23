<?php

namespace App\DataFixtures;

use App\Entity\Car;
use App\Entity\Detailpersonnel;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\Passage;
use App\Entity\Personnel;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les voyages — le cœur du jeu de données.
 *
 * Le statut d'un voyage n'est PAS une colonne : il se DÉDUIT des dates ('datedepartreelle',
 * 'datearriveereelle') et de la position du car. Le jeu couvre donc chaque état par lequel passe
 * réellement un départ, parce que les gardes ('VoyageGuard') et les calculs (capacité, échéances,
 * ponctualité) ne se comportent pas pareil selon l'état :
 *
 *   V1 · CLÔTURÉ (hier)      — passages complets, retards mesurables → ponctualité, recette, bordereaux
 *   V2 · EN ROUTE            — le car est À Bouaké, commercial à bord → vente à bord, suivi, revente
 *   V3 · PLANIFIÉ (demain)   — support des réservations (en attente, payées, no-show)
 *   V4 · DÉPART PARTIEL      — provenance effective = Bouaké → ventes bornées à l'aval
 *   V5 · SANS CAR NI ÉQUIPAGE— matière à l'alerte 'VOYAGE_SANS_PERSONNEL'
 *   V6 · RETOUR (après-demain)
 *
 * LES HEURES DE V2 ET V4 SONT RELATIVES À L'INSTANT DU CHARGEMENT. Avec des heures fixes, un jeu
 * chargé à 8 h afficherait un car « arrivé à Bouaké à 12 h 30 » — dans le futur. En ancrant sur
 * 'now', le scénario reste vrai quelle que soit l'heure de chargement.
 *
 * Les PASSAGES portent les horaires réels par gare. Le retard et le temps d'arrêt en sont dérivés à
 * la lecture, jamais stockés : il suffit donc que les heures réelles soient plausibles par rapport
 * aux heures prévues (elles-mêmes issues de la somme des tronçons).
 */
class VoyageFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    public function getDependencies(): array
    {
        return [ExploitationFixtures::class, FlotteFixtures::class, PersonnelFixtures::class, UserFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $planificateur = $this->getReference(Refs::user(Refs::IRA, 'exploitation'), User::class);

        $maintenant = new DateTimeImmutable();
        $hier = new DateTimeImmutable('yesterday');
        $demain = new DateTimeImmutable('tomorrow');
        $apresDemain = $demain->modify('+1 day');

        // ============================================================ V1 · clôturé (hier) ======
        $v1 = $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v1', 'abidjan-korhogo',
            'LI-ABI-KOR-0001-V1', 'car1', 'abidjan',
            $hier->setTime(7, 0), $hier->setTime(16, 0), $planificateur);
        $v1
            ->setDatedepartreelle($hier->setTime(7, 12))
            ->setDatearriveereelle($hier->setTime(16, 35))
            // Le car est allé au bout : sa position courante est le terminus.
            ->setGarecourante($this->getReference(Refs::gare(Refs::IRA, 'korhogo'), Gare::class));

        $this->affecter($manager, $identreprise, $v1, ['chauffeur1' => 'Conduite', 'convoyeur1' => 'Accompagnement']);

        // Prévu : Abidjan 07:00 · Yamoussoukro 10:00 · Bouaké 12:00 · Korhogo 16:00 (somme des tronçons).
        $this->tracerPassages($manager, $identreprise, $v1, [
            ['abidjan', null, $hier->setTime(7, 12)],
            ['yamoussoukro', $hier->setTime(10, 20), $hier->setTime(10, 33)],
            ['bouake', $hier->setTime(12, 38), $hier->setTime(12, 52)],
            ['korhogo', $hier->setTime(16, 35), null],
        ]);

        // ====================================================== V2 · en route (le car est à Bouaké)
        $departV2 = $maintenant->modify('-5 hours -30 minutes');
        $v2 = $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v2', 'abidjan-korhogo',
            'LI-ABI-KOR-0001-V2', 'car2', 'abidjan',
            $departV2, $departV2->modify('+9 hours'), $planificateur);
        $v2
            ->setDatedepartreelle($departV2->modify('+10 minutes'))
            // Position réelle du car : Bouaké. C'est de là que le commercial vend.
            ->setGarecourante($this->getReference(Refs::gare(Refs::IRA, 'bouake'), Gare::class))
            ->setCommercial($this->getReference(Refs::user(Refs::IRA, 'commercial'), User::class));

        $this->affecter($manager, $identreprise, $v2, ['chauffeur2' => 'Conduite', 'convoyeur2' => 'Accompagnement']);

        // Arrivé à Bouaké il y a 20 min, PAS ENCORE REPARTI : la vente y est donc encore possible.
        $this->tracerPassages($manager, $identreprise, $v2, [
            ['abidjan', null, $departV2->modify('+10 minutes')],
            ['yamoussoukro', $departV2->modify('+3 hours +15 minutes'), $departV2->modify('+3 hours +25 minutes')],
            ['bouake', $maintenant->modify('-20 minutes'), null],
        ]);

        // ================================================= V3 · planifié demain (réservations) ==
        $v3 = $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v3', 'abidjan-korhogo',
            'LI-ABI-KOR-0001-V3', 'car4', 'abidjan',
            $demain->setTime(7, 0), $demain->setTime(16, 0), $planificateur);
        $this->affecter($manager, $identreprise, $v3, ['chauffeur3' => 'Conduite', 'convoyeur3' => 'Accompagnement']);

        // ============================================== V4 · DÉPART PARTIEL depuis Bouaké ======
        // La gare de Bouaké lance son propre départ : la provenance effective est Bouaké, et
        // l'heure de départ prévue est celle de BOUAKÉ (pas celle de l'origine de la ligne).
        // Korhogo est alors attendue à +4 h (le seul tronçon restant).
        $departV4 = $maintenant->modify('+2 hours');
        $v4 = $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v4', 'abidjan-korhogo',
            'LI-ABI-KOR-0001-V4', 'car1', 'bouake',
            $departV4, $departV4->modify('+4 hours'), $planificateur);
        $this->affecter($manager, $identreprise, $v4, ['chauffeur4' => 'Conduite']);

        // ============================ V5 · départ imminent, ni car ni équipage (alerte) =========
        // Placé dans les 90 minutes : 'AlerteGenerationService' ne signale un voyage sans personnel
        // qu'à l'approche du départ (fenêtre de 120 min). Programmé pour demain, il resterait muet
        // et la situation d'alerte ne serait pas démontrable au chargement.
        $departV5 = $maintenant->modify('+90 minutes');
        $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v5', 'abidjan-daloa',
            'LI-ABI-DAL-0002-V1', null, 'abidjan',
            $departV5, $departV5->modify('+5 hours'), $planificateur);

        // ======================================================= V6 · retour, après-demain =====
        $v6 = $this->creerVoyage($manager, Refs::IRA, $identreprise, 'v6', 'korhogo-abidjan',
            'LI-KOR-ABI-0003-V1', 'car5', 'korhogo',
            $apresDemain->setTime(8, 0), $apresDemain->setTime(17, 0), $planificateur);
        $this->affecter($manager, $identreprise, $v6, ['chauffeur1' => 'Conduite', 'convoyeur1' => 'Accompagnement']);

        // ================================================================== Seconde compagnie ==
        $sahelId = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class)->getId();
        $this->creerVoyage($manager, Refs::SAHEL, $sahelId, 'v1', 'ferke-ouangolo',
            'LI-FER-OUA-0001-V1', 'car1', 'ferkessedougou',
            $demain->setTime(6, 30), $demain->setTime(8, 0),
            $this->getReference(Refs::user(Refs::SAHEL, 'admin'), User::class));

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * @param string|null $codeCar null = voyage sans véhicule affecté (capacité nulle)
     */
    private function creerVoyage(
        ObjectManager $manager,
        string $cie,
        int $identreprise,
        string $code,
        string $codeLigne,
        string $codevoyage,
        ?string $codeCar,
        string $codeGareProvenance,
        DateTimeImmutable $departPrevu,
        DateTimeImmutable $arriveePrevue,
        User $auteur
    ): Voyage {
        $ligne = $this->getReference(Refs::ligne($cie, $codeLigne), Ligne::class);
        $provenance = $this->getReference(Refs::gare($cie, $codeGareProvenance), Gare::class);

        $voyage = new Voyage();
        $voyage
            ->setCodevoyage($codevoyage)
            ->setLigne($ligne)
            // Provenance EFFECTIVE : origine de la ligne pour un départ normal, gare intermédiaire
            // pour un départ partiel. Tous les calculs d'heure de passage s'y réfèrent.
            ->setGareprovenance($provenance)
            ->setGarecourante($provenance)
            ->setProvenance($provenance->getLibelle())
            ->setDestination($ligne->getGareterminus()->getLibelle())
            ->setDatedepartprevue($departPrevu)
            ->setDatearriveeprevue($arriveePrevue)
            ->setIdentreprise($identreprise);
        $voyage->setCreatedBy($auteur->getId());

        if ($codeCar !== null) {
            $car = $this->getReference(Refs::car($cie, $codeCar), Car::class);
            $places = $car->getNbrsiege() ?? 0;
            $voyage
                ->setCar($car)
                ->setPlacesTotal($places)
                ->setPlacesprevues($places);
        } else {
            // Sans véhicule, la capacité est nulle : aucune vente ne peut passer.
            $voyage->setPlacesTotal(0);
        }

        // Un départ se prépare la veille : c'est la date de création réaliste.
        $manager->persist($this->daterA($voyage, $departPrevu->modify('-1 day')));
        $this->addReference(Refs::voyage($cie, $code), $voyage);

        return $voyage;
    }

    /**
     * Affecte l'équipage via 'Detailpersonnel' : personnel et voyage ne sont pas liés directement.
     *
     * @param array<string, string> $equipage code de personnel => motif de l'affectation
     */
    private function affecter(ObjectManager $manager, int $identreprise, Voyage $voyage, array $equipage): void
    {
        foreach ($equipage as $codePersonnel => $motif) {
            $detail = (new Detailpersonnel())
                ->setPersonnel($this->getReference(Refs::personnel(Refs::IRA, $codePersonnel), Personnel::class))
                ->setVoyage($voyage)
                ->setMotif($motif);
            $detail->setIdentreprise($identreprise);
            $manager->persist($detail);
        }
    }

    /**
     * Horaires RÉELS par gare. Un enregistrement par voyage × gare (contrainte d'unicité).
     * Une arrivée nulle à l'origine et un départ nul au terminus sont NORMAUX : le car ne fait
     * qu'y partir, ou qu'y arriver.
     *
     * @param list<array{0: string, 1: DateTimeImmutable|null, 2: DateTimeImmutable|null}> $etapes
     */
    private function tracerPassages(ObjectManager $manager, int $identreprise, Voyage $voyage, array $etapes): void
    {
        foreach ($etapes as [$codeGare, $arrivee, $depart]) {
            $passage = (new Passage())
                ->setVoyage($voyage)
                ->setGare($this->getReference(Refs::gare(Refs::IRA, $codeGare), Gare::class))
                ->setArriveeReelle($arrivee)
                ->setDepartReelle($depart)
                ->setIdentreprise($identreprise);
            // 'Passage' n'étend pas 'EntityBase' : son 'createdAt' est posé par le constructeur,
            // il n'y a rien à antidater.
            $manager->persist($passage);
        }
    }
}

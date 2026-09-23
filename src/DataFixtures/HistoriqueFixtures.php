<?php

namespace App\DataFixtures;

use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\TicketStatus;
use App\Entity\Bagage;
use App\Entity\Car;
use App\Entity\Client;
use App\Entity\Detailpersonnel;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\Passage;
use App\Entity\Personnel;
use App\Entity\Siege;
use App\Entity\Tarifbagage;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Un mois de départs passés, clôturés, avec leurs billets et leurs passages horodatés.
 *
 * POURQUOI : sans profondeur d'historique, tout le module « Tableau de bord & Rapports » est vide de
 * sens — une recette sur 30 jours qui ne compte qu'une journée, un taux de ponctualité calculé sur
 * un seul voyage, une matrice origine-destination à quatre cellules. Ce sont pourtant les écrans
 * que l'on regarde en premier.
 *
 * Les valeurs sont PSEUDO-ALÉATOIRES MAIS DÉTERMINISTES ('crc32' sur l'index du jour) : deux
 * chargements produisent exactement le même jeu. Un 'rand()' rendrait tout bug de calcul
 * irreproductible d'une exécution à l'autre.
 *
 * L'historique sert aussi à la FIDÉLITÉ : le client membre ('c5') voyage sur les dix premiers
 * départs, ce qui lui fait franchir le seuil de dix voyages. Son état étant entièrement dérivé de
 * ses billets, c'est la seule façon honnête de lui donner une récompense acquise.
 */
class HistoriqueFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    /** Nombre de jours d'historique (J-30 à J-2 ; J-1 et J sont couverts par 'VoyageFixtures'). */
    private const JOURS = 30;

    /** Premier numéro de voyage libre sur la ligne Abidjan → Korhogo ('VoyageFixtures' a pris V1 à V4). */
    private const PREMIER_NUMERO = 5;

    /** Les tronçons vendables et leur prix dans la grille : [montée, descente, prix]. */
    private const TRONCONS = [
        ['abidjan', 'korhogo', 15000],
        ['abidjan', 'bouake', 8000],
        ['abidjan', 'yamoussoukro', 5000],
        ['yamoussoukro', 'korhogo', 11000],
        ['bouake', 'korhogo', 8000],
    ];

    /** Quelle gare vend quoi : le billet est émis par un agent de la gare de MONTÉE. */
    private const AGENT_PAR_GARE = [
        'abidjan' => 'agent-abidjan',
        'yamoussoukro' => 'agent-yamoussoukro',
        'bouake' => 'agent-bouake',
    ];

    private const CARS = ['car1', 'car2', 'car5'];

    private const CHAUFFEURS = ['chauffeur1', 'chauffeur2', 'chauffeur3', 'chauffeur4'];

    private const CONVOYEURS = ['convoyeur1', 'convoyeur2', 'convoyeur3'];

    private const CLIENTS = ['c1', 'c2', 'c3', 'c4', 'c6', 'c7', 'c8'];

    /**
     * Voyageurs OCCASIONNELS créés pour l'occasion.
     *
     * Sans eux, les huit clients nommés se partageraient les ~230 billets de l'historique et
     * afficheraient trente voyages chacun : une clientèle de grands habitués qui n'existe nulle
     * part, et surtout un membre du programme de fidélité impossible à distinguer des autres. Avec
     * une longue traîne de voyageurs à deux ou trois trajets, le membre ressort — et les
     * statistiques clients ressemblent à ce qu'on observe vraiment.
     */
    private const NOMBRE_OCCASIONNELS = 80;

    private const NOMS = [
        'Kouassi', 'Kouamé', 'Yao', 'Konan', 'Koffi', 'N\'Guessan', 'Aka', 'Assi', 'Traoré',
        'Coulibaly', 'Ouattara', 'Bamba', 'Diarra', 'Fofana', 'Sangaré', 'Cissé', 'Doumbia',
        'Sanogo', 'Silué', 'Soro', 'Gnamien', 'Tanoh', 'Zadi', 'Guei', 'Yapi', 'Amani', 'Brou',
        'Ehouman',
    ];

    private const PRENOMS = [
        'Ange', 'Marie', 'Serge', 'Awa', 'Ibrahim', 'Estelle', 'Franck', 'Prisca', 'Olivier',
        'Aminata', 'Roland', 'Abou', 'Désiré', 'Vamara', 'Fatou', 'Moussa', 'Rokia', 'Yacouba',
        'Christelle', 'Bernadette', 'Karim', 'Séverin', 'Éric', 'Lassina', 'Paul', 'Adama',
        'Mariam', 'Justin',
    ];

    /** @var list<Client> voyageurs occasionnels, tirés au sort pour chaque billet de l'historique */
    private array $occasionnels = [];

    private int $compteurBagages = 100;

    public function getDependencies(): array
    {
        return [BilletterieFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['app'];
    }

    public function load(ObjectManager $manager): void
    {
        $identreprise = $this->getReference(Refs::entreprise(Refs::IRA), Entreprise::class)->getId();
        $planificateur = $this->getReference(Refs::user(Refs::IRA, 'exploitation'), User::class);
        $ligne = $this->getReference(Refs::ligne(Refs::IRA, 'abidjan-korhogo'), Ligne::class);
        $aujourdhui = new DateTimeImmutable('today');

        $this->creerVoyageursOccasionnels($manager, $identreprise);

        $numero = self::PREMIER_NUMERO;

        // J-30 à J-2 : on s'arrête à J-2, la veille et le jour même étant déjà scénarisés.
        for ($recul = self::JOURS; $recul >= 2; $recul--) {
            $jour = $aujourdhui->modify("-$recul days");
            $index = self::JOURS - $recul; // 0, 1, 2… : la graine du déterminisme

            $codeCar = self::CARS[$index % count(self::CARS)];
            $car = $this->getReference(Refs::car(Refs::IRA, $codeCar), Car::class);

            // Retard au départ : 0 à 25 min ; il se creuse un peu le long du trajet.
            $retardDepart = $this->alea($index, 0, 25);
            $departPrevu = $jour->setTime(7, 0);
            $departReel = $departPrevu->modify("+$retardDepart minutes");

            $voyage = new Voyage();
            $voyage
                ->setCodevoyage(sprintf('%s-V%d', $ligne->getCodeligne(), $numero++))
                // Un seul départ par jour dans l'historique : Abidjan ne lance qu'un car quotidien sur
                // cette ligne, c'est donc toujours le « départ 1 » de sa journée. Le compteur du
                // numéro de départ repart à 1 chaque jour (cf. NumeroDepartService).
                ->setNumerodepart(1)
                ->setLigne($ligne)
                ->setGareprovenance($this->gare('abidjan'))
                // Voyage terminé : le car est au terminus.
                ->setGarecourante($this->gare('korhogo'))
                ->setProvenance($this->gare('abidjan')->getLibelle())
                ->setDestination($this->gare('korhogo')->getLibelle())
                ->setDatedepartprevue($departPrevu)
                ->setDatearriveeprevue($jour->setTime(16, 0))
                ->setDatedepartreelle($departReel)
                ->setDatearriveereelle($jour->setTime(16, 0)->modify('+' . $this->alea($index + 500, 0, 55) . ' minutes'))
                ->setCar($car)
                ->setPlacesTotal($car->getNbrsiege())
                ->setPlacesprevues($car->getNbrsiege())
                ->setIdentreprise($identreprise);
            $voyage->setCreatedBy($planificateur->getId());
            $manager->persist($this->daterA($voyage, $departPrevu->modify('-1 day')));

            $this->affecterEquipage($manager, $identreprise, $voyage, $index);
            $this->tracerPassages($manager, $identreprise, $voyage, $jour, $index, $retardDepart);
            $this->vendreBillets($manager, $identreprise, $voyage, $codeCar, $jour, $index);
        }

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    /**
     * La longue traîne de voyageurs. Le contact reste la clé métier : il doit être unique, sinon
     * 'ClientResolver' rattacherait plusieurs personnes à la même fiche.
     */
    private function creerVoyageursOccasionnels(ObjectManager $manager, int $identreprise): void
    {
        for ($i = 0; $i < self::NOMBRE_OCCASIONNELS; $i++) {
            $numero = (string) (20000000 + $i); // 8 chiffres : garantit l'unicité du contact

            $client = (new Client())
                ->setNom(sprintf(
                    '%s %s',
                    self::NOMS[$i % count(self::NOMS)],
                    self::PRENOMS[intdiv($i, count(self::NOMS)) % count(self::PRENOMS)]
                ))
                ->setContact(sprintf(
                    '+225 07 %s %s %s %s',
                    substr($numero, 0, 2),
                    substr($numero, 2, 2),
                    substr($numero, 4, 2),
                    substr($numero, 6, 2)
                ))
                ->setFidelite(false)
                ->setIdentreprise($identreprise);

            $manager->persist($client);
            $this->occasionnels[] = $client;
        }
    }

    private function affecterEquipage(ObjectManager $manager, int $identreprise, Voyage $voyage, int $index): void
    {
        $equipage = [
            self::CHAUFFEURS[$index % count(self::CHAUFFEURS)] => 'Conduite',
            self::CONVOYEURS[$index % count(self::CONVOYEURS)] => 'Accompagnement',
        ];

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
     * Horaires réels aux quatre arrêts. Les heures PRÉVUES découlent des tronçons de la ligne
     * (07:00 · 10:00 · 12:00 · 16:00) ; on y ajoute un retard qui s'accumule, sauf rattrapage.
     */
    private function tracerPassages(
        ObjectManager $manager,
        int $identreprise,
        Voyage $voyage,
        DateTimeImmutable $jour,
        int $index,
        int $retardDepart
    ): void {
        $retardYamoussoukro = max(0, $retardDepart + $this->alea($index + 10, -5, 20));
        $retardBouake = max(0, $retardYamoussoukro + $this->alea($index + 20, -10, 20));

        $etapes = [
            ['abidjan', null, $voyage->getDatedepartreelle()],
            [
                'yamoussoukro',
                $jour->setTime(10, 0)->modify("+$retardYamoussoukro minutes"),
                $jour->setTime(10, 0)->modify('+' . ($retardYamoussoukro + $this->alea($index + 30, 8, 20)) . ' minutes'),
            ],
            [
                'bouake',
                $jour->setTime(12, 0)->modify("+$retardBouake minutes"),
                $jour->setTime(12, 0)->modify('+' . ($retardBouake + $this->alea($index + 40, 10, 25)) . ' minutes'),
            ],
            ['korhogo', $voyage->getDatearriveereelle(), null],
        ];

        foreach ($etapes as [$codeGare, $arrivee, $depart]) {
            $passage = (new Passage())
                ->setVoyage($voyage)
                ->setGare($this->gare($codeGare))
                ->setArriveeReelle($arrivee)
                ->setDepartReelle($depart)
                ->setIdentreprise($identreprise);
            $manager->persist($passage);
        }
    }

    private function vendreBillets(
        ObjectManager $manager,
        int $identreprise,
        Voyage $voyage,
        string $codeCar,
        DateTimeImmutable $jour,
        int $index
    ): void {
        $nombre = $this->alea($index + 100, 5, 12);
        $numeroBillet = 0;

        for ($i = 0; $i < $nombre; $i++) {
            [$montee, $descente, $prix] = self::TRONCONS[$this->alea($index * 31 + $i, 0, count(self::TRONCONS) - 1)];

            // Le client membre occupe la première place des douze premiers départs : c'est ce qui
            // lui fait franchir le seuil de fidélité (compteur dérivé de ses billets, jamais stocké).
            $estMembre = $i === 0 && $index < 12;

            $agent = $this->getReference(
                Refs::user(Refs::IRA, self::AGENT_PAR_GARE[$montee]),
                User::class
            );
            // Vente le matin même, avant le passage du car à la gare de montée.
            $vendu = $jour->setTime(5, 30)->modify('+' . $this->alea($index * 7 + $i, 0, 80) . ' minutes');

            // Une annulation de temps en temps : matière au taux de désistement par agent. Jamais
            // sur le billet du membre, sinon son compteur de fidélité perdrait un voyage.
            $annule = !$estMembre && ($index + $i) % 23 === 0;
            // Une remise de temps en temps : matière au suivi anti-abus des remises.
            $remise = !$annule && ($index + $i) % 17 === 0 ? (int) round($prix * 0.1) : 0;

            $client = $this->choisirClient($estMembre, $index, $i);

            $ticket = new Ticket();
            $ticket
                ->setVoyage($voyage)
                // Un siège distinct par billet : aucun conflit d'occupation à créer ici.
                ->setSiege($this->getReference(Refs::siege(Refs::IRA, $codeCar, $i + 1), Siege::class))
                ->setGare($this->gare($montee))
                ->setGaredescente($this->gare($descente))
                ->setPrix($prix - $remise)
                ->setRemise($remise)
                ->setRemisetype($remise > 0 ? 'POURCENTAGE' : null)
                ->setRemisevaleur($remise > 0 ? 10 : null)
                ->setStatut($annule ? TicketStatus::STATUT_ANNULE->value : TicketStatus::STATUT_VALIDE->value)
                ->setCodeticket(sprintf('%s-TCK-%s-%d', $voyage->getCodevoyage(), $jour->format('Y'), ++$numeroBillet))
                ->setClient($client)
                ->setNomclient($client->getNom())
                ->setContactclient($client->getContact())
                ->setIdentreprise($identreprise);
            $ticket->setCreatedBy($agent->getId());

            if ($annule) {
                $ticket
                    ->setDatedesistement($vendu->modify('+40 minutes'))
                    ->setMotifdesistement('Annulation au guichet avant le départ');
            }

            $manager->persist($this->daterA($ticket, $vendu));

            // Un bagage sur quatre billets environ.
            if (!$annule && ($index + $i) % 4 === 0) {
                $this->enregistrerBagage($manager, $identreprise, $ticket, $agent, $vendu, $index + $i);
            }
        }
    }

    /**
     * Le membre du programme d'abord ; sinon un habitué nommé une fois sur cinq, et un voyageur
     * occasionnel le reste du temps — ce qui produit la longue traîne attendue.
     */
    private function choisirClient(bool $estMembre, int $index, int $rang): Client
    {
        if ($estMembre) {
            return $this->getReference(Refs::client(Refs::IRA, 'c5'), Client::class);
        }

        if ($this->alea($index * 13 + $rang, 0, 4) === 0) {
            $code = self::CLIENTS[$this->alea($index * 17 + $rang, 0, count(self::CLIENTS) - 1)];

            return $this->getReference(Refs::client(Refs::IRA, $code), Client::class);
        }

        return $this->occasionnels[$this->alea($index * 29 + $rang, 0, count($this->occasionnels) - 1)];
    }

    private function enregistrerBagage(
        ObjectManager $manager,
        int $identreprise,
        Ticket $ticket,
        User $agent,
        DateTimeImmutable $vendu,
        int $graine
    ): void {
        $poids = $this->alea($graine + 700, 3, 34);
        [$codeTarif, $montant] = match (true) {
            $poids <= 10 => ['leger', 1000],
            $poids <= 25 => ['moyen', 2500],
            default => ['lourd', 5000],
        };

        $bagage = (new Bagage())
            ->setCodebagage(sprintf('BAG-%s-%d', $vendu->format('Y'), ++$this->compteurBagages))
            ->setNature(['Valise', 'Sac de voyage', 'Carton', 'Sac à dos'][$this->alea($graine + 800, 0, 3)])
            ->setType($poids > 25 ? 'LOURD' : 'LEGER')
            ->setPoids($poids)
            ->setMontant($montant)
            ->setMontantforce(false)
            ->setTicket($ticket)
            ->setVoyage($ticket->getVoyage())
            ->setGaredepart($ticket->getGare())
            ->setGaredescente($ticket->getGaredescente())
            ->setTarifbagage($this->getReference(Refs::tarifbagage(Refs::IRA, $codeTarif), Tarifbagage::class))
            // Voyage terminé : le bagage a été remis à l'arrivée.
            ->setStatut(BagageStatus::STATUT_LIVRE->value)
            ->setIdentreprise($identreprise);
        $bagage->setCreatedBy($agent->getId());

        $manager->persist($this->daterA($bagage, $vendu->modify('+2 minutes')));
    }

    private function gare(string $code): Gare
    {
        return $this->getReference(Refs::gare(Refs::IRA, $code), Gare::class);
    }

    /**
     * Tirage DÉTERMINISTE dans [$min, $max] : même graine, même résultat, à chaque chargement.
     */
    private function alea(int $graine, int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $min + (int) (crc32('itransport-' . $graine) % ($max - $min + 1));
    }
}

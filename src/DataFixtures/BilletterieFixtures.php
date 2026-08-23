<?php

namespace App\DataFixtures;

use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\BeneficiaireCategorie;
use App\Domain\Enum\TicketStatus;
use App\Entity\Bagage;
use App\Entity\Beneficiaire;
use App\Entity\Client;
use App\Entity\Entreprise;
use App\Entity\Gare;
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
 * Clients, bénéficiaires de remise, grille bagages, billets et bagages.
 *
 * Les billets sont émis PAR TRONÇON (gare de montée → gare de descente vendue) et leur prix vient
 * de la grille tarifaire globale. Le jeu couvre les cas qui font la spécificité du module :
 *
 *  - VENTE INTERMÉDIAIRE : un billet émis depuis Yamoussoukro sur un voyage parti d'Abidjan ;
 *  - DESCENTE ANTICIPÉE + REVENTE : un passager descend à Bouaké avant son terminus, et son siège
 *    est REVENDU sur le tronçon aval par le commercial à bord. C'est le scénario qui prouve que
 *    l'occupation se juge au point de montée et non sur l'ensemble du trajet ;
 *  - REMISE avec bénéficiaire (auditée) ;
 *  - DÉSISTEMENT dans ses deux formes : ANNULE (remboursé) et REPORTE (nouveau billet chaîné par
 *    'ticketOrigine') ;
 *  - VENTE À BORD par le commercial, dont la recette revient à sa gare d'affectation.
 *
 * Le champ 'evince' n'est jamais renseigné : l'éviction est DÉRIVÉE par 'CapaciteService' à la
 * lecture. L'écrire en base créerait une valeur qui ne s'éteindrait pas toute seule.
 */
class BilletterieFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    /** @var array<string, int> compteur de billets par code de voyage (le n° de billet est par voyage) */
    private array $compteurs = [];

    private int $compteurBagages = 0;

    /** code => [nom, contact, e-mail, membre fidélité] */
    private const CLIENTS = [
        'c1' => ['Kouadio Ange', '+225 07 00 00 00 01', 'ange.kouadio@example.ci', false],
        'c2' => ['Bintou Diarra', '+225 07 00 00 00 02', null, false],
        'c3' => ['Yao Patricia', '+225 07 00 00 00 03', 'p.yao@example.ci', false],
        'c4' => ['Ibrahim Sanogo', '+225 07 00 00 00 04', null, false],
        // Membre du programme de fidélité : son compteur de voyages est DÉRIVÉ de ses billets.
        'c5' => ['Koné Salif', '+225 07 00 00 00 05', 'salif.kone@example.ci', true],
        'c6' => ['Adjoua Estelle', '+225 07 00 00 00 06', null, false],
        'c7' => ['Moussa Bamba', '+225 07 00 00 00 07', null, false],
        'c8' => ['Aya Konan', '+225 07 00 00 00 08', 'aya.konan@example.ci', false],
    ];

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
        $maintenant = new DateTimeImmutable();
        $hier = new DateTimeImmutable('yesterday');

        $agentAbidjan = $this->getReference(Refs::user(Refs::IRA, 'agent-abidjan'), User::class);
        $agentYamoussoukro = $this->getReference(Refs::user(Refs::IRA, 'agent-yamoussoukro'), User::class);
        $commercial = $this->getReference(Refs::user(Refs::IRA, 'commercial'), User::class);

        $this->creerClients($manager, Refs::IRA, $identreprise);
        $this->creerBeneficiaires($manager, Refs::IRA, $identreprise);
        $this->creerGrilleBagages($manager, Refs::IRA, $identreprise);

        $v1 = $this->getReference(Refs::voyage(Refs::IRA, 'v1'), Voyage::class);
        $v2 = $this->getReference(Refs::voyage(Refs::IRA, 'v2'), Voyage::class);
        $v3 = $this->getReference(Refs::voyage(Refs::IRA, 'v3'), Voyage::class);

        // ============================================== V1 · voyage clôturé d'hier (recette faite)
        $venteV1 = $hier->setTime(6, 20);

        $this->creerTicket($manager, $identreprise, 't1', $v1, 'car1', 1, 'c1',
            'abidjan', 'korhogo', 15000, $agentAbidjan, $venteV1);

        $this->creerTicket($manager, $identreprise, 't2', $v1, 'car1', 2, 'c2',
            'abidjan', 'bouake', 8000, $agentAbidjan, $venteV1->modify('+8 minutes'));

        // Remise de 10 % accordée à une étudiante : le prix payé est le tarif MOINS la remise.
        $this->creerTicket($manager, $identreprise, 't3', $v1, 'car1', 3, 'c3',
            'abidjan', 'korhogo', 13500, $agentAbidjan, $venteV1->modify('+15 minutes'),
            remise: 1500, remisetype: 'POURCENTAGE', remisevaleur: 10, codeBeneficiaire: 'etudiant');

        // Vente depuis une gare INTERMÉDIAIRE, le jour même, avant le passage du car.
        $this->creerTicket($manager, $identreprise, 't4', $v1, 'car1', 4, 'c4',
            'yamoussoukro', 'korhogo', 11000, $agentYamoussoukro, $hier->setTime(9, 40));

        // Désistement AVEC remboursement : le siège a été libéré avant le départ.
        $this->creerTicket($manager, $identreprise, 't5', $v1, 'car1', 5, 'c6',
            'abidjan', 'bouake', 8000, $agentAbidjan, $venteV1->modify('+22 minutes'),
            statut: TicketStatus::STATUT_ANNULE,
            datedesistement: $hier->setTime(6, 55),
            motif: 'Client absent au départ — remboursement intégral au guichet');

        // ================================================ V2 · voyage en route (car à Bouaké) ===
        $venteV2 = $v2->getDatedepartprevue()->modify('-45 minutes');

        $this->creerTicket($manager, $identreprise, 't6', $v2, 'car2', 1, 'c5',
            'abidjan', 'korhogo', 15000, $agentAbidjan, $venteV2);

        $this->creerTicket($manager, $identreprise, 't7', $v2, 'car2', 2, 'c7',
            'abidjan', 'yamoussoukro', 5000, $agentAbidjan, $venteV2->modify('+5 minutes'));

        // DESCENTE ANTICIPÉE : billet vendu jusqu'à Korhogo, passager descendu à Bouaké.
        // Le siège 3 redevient donc disponible sur le tronçon Bouaké → Korhogo.
        $t8 = $this->creerTicket($manager, $identreprise, 't8', $v2, 'car2', 3, 'c8',
            'abidjan', 'korhogo', 15000, $agentAbidjan, $venteV2->modify('+9 minutes'));
        $t8->setGaredescentereelle($this->getReference(Refs::gare(Refs::IRA, 'bouake'), Gare::class));

        // REVENTE du siège 3 par le commercial, depuis la position réelle du car (Bouaké).
        // Sa recette reviendra à la gare d'AFFECTATION du commercial, pas à Bouaké.
        $this->creerTicket($manager, $identreprise, 't9', $v2, 'car2', 3, null,
            'bouake', 'korhogo', 8000, $commercial, $maintenant->modify('-15 minutes'),
            commercial: $commercial, nomClient: 'Passager Bouaké', contactClient: '+225 07 00 00 00 09');

        // Désistement avec REPORT : le billet d'origine passe à REPORTE, un nouveau est émis sur V3.
        $t10 = $this->creerTicket($manager, $identreprise, 't10', $v2, 'car2', 6, 'c2',
            'abidjan', 'korhogo', 15000, $agentAbidjan, $venteV2->modify('+12 minutes'),
            statut: TicketStatus::STATUT_REPORTE,
            datedesistement: $v2->getDatedepartprevue()->modify('-20 minutes'),
            motif: 'Report demandé par le client sur le départ de demain');

        // ================================================= V3 · départ de demain (billet reporté)
        $t11 = $this->creerTicket($manager, $identreprise, 't11', $v3, 'car4', 1, 'c2',
            'abidjan', 'korhogo', 15000, $agentAbidjan, $v2->getDatedepartprevue()->modify('-20 minutes'));
        // Chaînage du report : c'est ce lien qui distingue un report d'une vente ordinaire.
        $t11->setTicketOrigine($t10);

        // ========================================================================== Bagages =====
        // Un bagage SUIT son billet (voyage, gares, identité) ; son tarif vient du poids.
        $this->creerBagage($manager, $identreprise, 'b1', $t8, 'moyen', 'Valise rigide', 'LOURD', 18, 2500,
            $agentAbidjan, $venteV2->modify('+10 minutes'), BagageStatus::STATUT_EMBARQUE);

        $this->creerBagage($manager, $identreprise, 'b2',
            $this->getReference(Refs::ticket(Refs::IRA, 't1'), Ticket::class),
            'leger', 'Sac à dos', 'LEGER', 7, 1000,
            $agentAbidjan, $venteV1->modify('+3 minutes'), BagageStatus::STATUT_LIVRE);

        // Montant FORCÉ par l'agent (au-dessus de la grille) : tracé et audité.
        $this->creerBagage($manager, $identreprise, 'b3',
            $this->getReference(Refs::ticket(Refs::IRA, 't4'), Ticket::class),
            'lourd', 'Carton de marchandises', 'VOLUMINEUX', 32, 7000,
            $agentYamoussoukro, $hier->setTime(9, 45), BagageStatus::STATUT_LIVRE, montantForce: true);

        // Bagage vendu à bord par le commercial, en même temps que le billet.
        $this->creerBagage($manager, $identreprise, 'b4',
            $this->getReference(Refs::ticket(Refs::IRA, 't9'), Ticket::class),
            'leger', 'Sac de voyage', 'LEGER', 9, 1000,
            $commercial, $maintenant->modify('-14 minutes'), BagageStatus::STATUT_ENREGISTRE,
            commercial: $commercial);

        // ================================================================== Seconde compagnie ==
        $sahelId = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class)->getId();
        $clientSahel = (new Client())
            ->setNom('Sekongo Tenena')
            ->setContact('+225 07 55 44 33 22')
            ->setFidelite(false)
            ->setIdentreprise($sahelId);
        $manager->persist($clientSahel);
        $this->addReference(Refs::client(Refs::SAHEL, 'c1'), $clientSahel);

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    private function creerClients(ObjectManager $manager, string $cie, int $identreprise): void
    {
        $adhesion = (new DateTimeImmutable('today'))->modify('-6 months');

        foreach (self::CLIENTS as $code => [$nom, $contact, $email, $fidelite]) {
            $client = (new Client())
                ->setNom($nom)
                // Le contact est la CLÉ métier du client : c'est par lui que 'ClientResolver' retrouve
                // ou crée la fiche à chaque vente.
                ->setContact($contact)
                ->setEmail($email)
                ->setFidelite($fidelite)
                ->setIdentreprise($identreprise);

            if ($fidelite) {
                $client
                    ->setCartefidelite('FID-IRA-0001')
                    ->setDateadhesion($adhesion);
            }

            $manager->persist($client);
            $this->addReference(Refs::client($cie, $code), $client);
        }
    }

    private function creerBeneficiaires(ObjectManager $manager, string $cie, int $identreprise): void
    {
        $beneficiaires = [
            'etudiant' => ['Étudiants (convention UVCI)', BeneficiaireCategorie::ETUDIANT, '+225 27 22 44 55 66'],
            'militaire' => ['Personnel militaire', BeneficiaireCategorie::MILITAIRE, null],
            'personnel' => ['Personnel IRA Transport', BeneficiaireCategorie::PERSONNEL, null],
            'partenaire' => ['Partenaire hôtelier', BeneficiaireCategorie::PARTENAIRE, '+225 27 22 44 55 77'],
        ];

        foreach ($beneficiaires as $code => [$nom, $categorie, $contact]) {
            $beneficiaire = (new Beneficiaire())
                ->setNom($nom)
                ->setCategorie($categorie->value)
                ->setContact($contact)
                ->setIdentreprise($identreprise);
            $manager->persist($beneficiaire);
            $this->addReference(Refs::beneficiaire($cie, $code), $beneficiaire);
        }
    }

    /**
     * Tranches de poids. La DERNIÈRE est ouverte ('poidsmax' à null) : sans elle, un bagage plus
     * lourd que la grille ne trouverait aucun tarif.
     */
    private function creerGrilleBagages(ObjectManager $manager, string $cie, int $identreprise): void
    {
        $tranches = [
            'leger' => ['Bagage léger (0 à 10 kg)', 0, 10, 1000],
            'moyen' => ['Bagage moyen (11 à 25 kg)', 11, 25, 2500],
            'lourd' => ['Bagage lourd (26 kg et plus)', 26, null, 5000],
        ];

        foreach ($tranches as $code => [$libelle, $min, $max, $montant]) {
            $tarif = (new Tarifbagage())
                ->setLibelle($libelle)
                ->setPoidsmin($min)
                ->setPoidsmax($max)
                ->setMontant($montant)
                ->setIdentreprise($identreprise);
            $manager->persist($tarif);
            $this->addReference(Refs::tarifbagage($cie, $code), $tarif);
        }
    }

    /**
     * Le numéro de billet reprend le format de 'TicketProcessor' : code du voyage + compteur par voyage.
     */
    private function creerTicket(
        ObjectManager $manager,
        int $identreprise,
        string $code,
        Voyage $voyage,
        string $codeCar,
        int $numeroSiege,
        ?string $codeClient,
        string $codeGareMontee,
        string $codeGareDescente,
        int $prix,
        User $vendeur,
        DateTimeImmutable $vendu,
        TicketStatus $statut = TicketStatus::STATUT_VALIDE,
        int $remise = 0,
        ?string $remisetype = null,
        ?int $remisevaleur = null,
        ?string $codeBeneficiaire = null,
        ?User $commercial = null,
        ?DateTimeImmutable $datedesistement = null,
        ?string $motif = null,
        ?string $nomClient = null,
        ?string $contactClient = null
    ): Ticket {
        $ticket = new Ticket();
        $ticket
            ->setVoyage($voyage)
            ->setSiege($this->getReference(Refs::siege(Refs::IRA, $codeCar, $numeroSiege), Siege::class))
            ->setGare($this->getReference(Refs::gare(Refs::IRA, $codeGareMontee), Gare::class))
            ->setGaredescente($this->getReference(Refs::gare(Refs::IRA, $codeGareDescente), Gare::class))
            ->setPrix($prix)
            ->setRemise($remise)
            ->setRemisetype($remisetype)
            ->setRemisevaleur($remisevaleur)
            ->setStatut($statut->value)
            ->setCodeticket(sprintf(
                '%s-TCK-%s-%d',
                $voyage->getCodevoyage(),
                $voyage->getDatedepartprevue()->format('Y'),
                $this->prochainNumero($voyage)
            ))
            ->setCommercial($commercial)
            ->setDatedesistement($datedesistement)
            ->setMotifdesistement($motif)
            ->setIdentreprise($identreprise);
        $ticket->setCreatedBy($vendeur->getId());

        if ($codeClient !== null) {
            $client = $this->getReference(Refs::client(Refs::IRA, $codeClient), Client::class);
            // L'identité est SNAPSHOTÉE sur le billet : elle doit rester lisible même si la fiche change.
            $ticket
                ->setClient($client)
                ->setNomclient($client->getNom())
                ->setContactclient($client->getContact());
        } else {
            $ticket->setNomclient($nomClient)->setContactclient($contactClient);
        }

        if ($codeBeneficiaire !== null) {
            $ticket->setBeneficiaire($this->getReference(Refs::beneficiaire(Refs::IRA, $codeBeneficiaire), Beneficiaire::class));
        }

        $manager->persist($this->daterA($ticket, $vendu));
        $this->addReference(Refs::ticket(Refs::IRA, $code), $ticket);

        return $ticket;
    }

    private function prochainNumero(Voyage $voyage): int
    {
        $cle = $voyage->getCodevoyage();

        return $this->compteurs[$cle] = ($this->compteurs[$cle] ?? 0) + 1;
    }

    private function creerBagage(
        ObjectManager $manager,
        int $identreprise,
        string $code,
        Ticket $ticket,
        string $codeTarif,
        string $nature,
        string $type,
        int $poids,
        int $montant,
        User $agent,
        DateTimeImmutable $enregistre,
        BagageStatus $statut,
        bool $montantForce = false,
        ?User $commercial = null
    ): void {
        $bagage = (new Bagage())
            ->setCodebagage(sprintf('BAG-%s-%d', $enregistre->format('Y'), ++$this->compteurBagages))
            ->setNature($nature)
            ->setType($type)
            ->setPoids($poids)
            ->setMontant($montant)
            ->setMontantforce($montantForce)
            // Le bagage SUIT son billet : voyage, gares et identité en sont déduits — l'identité
            // n'est d'ailleurs pas dupliquée sur le bagage, elle se lit à travers le ticket.
            ->setTicket($ticket)
            ->setVoyage($ticket->getVoyage())
            ->setGaredepart($ticket->getGare())
            ->setGaredescente($ticket->getGaredescente())
            ->setTarifbagage($this->getReference(Refs::tarifbagage(Refs::IRA, $codeTarif), Tarifbagage::class))
            ->setCommercial($commercial)
            ->setStatut($statut->value)
            ->setIdentreprise($identreprise);
        $bagage->setCreatedBy($agent->getId());

        $manager->persist($this->daterA($bagage, $enregistre));
        $this->addReference('bagage-' . Refs::IRA . '-' . $code, $bagage);
    }
}

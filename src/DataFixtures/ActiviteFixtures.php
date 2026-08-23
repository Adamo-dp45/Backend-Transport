<?php

namespace App\DataFixtures;

use App\Domain\Service\ActiviteLogger;
use App\Entity\Activite;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Depannage;
use App\Entity\Entreprise;
use App\Entity\Piece;
use App\Entity\Reservation;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Le journal d'activité — la trace des événements critiques et la base de l'anti-fraude.
 *
 * Chaque entrée correspond à un fait RÉELLEMENT présent dans le jeu de données (le billet annulé
 * existe, le dépannage annulé aussi) et pointe vers lui par 'cibletype' + 'cibleid'. Un journal
 * inventé, dont les lignes ne renvoient à rien, casserait les liens du centre de notifications et
 * donnerait des « actions critiques par agent » impossibles à recouper.
 *
 * On n'écrit ici que ce que 'ActiviteLogger' consigne effectivement : le statut d'un car, par
 * exemple, n'est pas journalisé (il découle déjà d'une affectation ou d'un dépannage, tous deux
 * tracés).
 */
class ActiviteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    public function getDependencies(): array
    {
        return [ReservationFixtures::class, CourrierFixtures::class, DepannageFixtures::class];
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
        $commercial = $this->getReference(Refs::user(Refs::IRA, 'commercial'), User::class);
        $exploitation = $this->getReference(Refs::user(Refs::IRA, 'exploitation'), User::class);
        $chefKorhogo = $this->getReference(Refs::user(Refs::IRA, 'chef-korhogo'), User::class);
        $responsableFlotte = $this->getReference(Refs::user(Refs::IRA, 'flotte'), User::class);
        $magasinier = $this->getReference(Refs::user(Refs::IRA, 'magasin'), User::class);

        $hier = new DateTimeImmutable('yesterday');
        $maintenant = new DateTimeImmutable();
        $jour = new DateTimeImmutable('today');

        $v1 = $this->getReference(Refs::voyage(Refs::IRA, 'v1'), Voyage::class);
        $v2 = $this->getReference(Refs::voyage(Refs::IRA, 'v2'), Voyage::class);

        // ------------------------------------------------------- Exploitation du voyage d'hier
        $this->journaliser($manager, $identreprise, ActiviteLogger::VOYAGE_DEPART,
            sprintf('Départ réel du voyage %s depuis la gare d\'Adjamé', $v1->getCodevoyage()),
            'Voyage', $v1->getId(), $agentAbidjan, $hier->setTime(7, 12));

        $this->journaliser($manager, $identreprise, ActiviteLogger::VOYAGE_RECEPTION,
            sprintf('Réception du voyage %s à la gare de Bouaké', $v1->getCodevoyage()),
            'Voyage', $v1->getId(), $this->getReference(Refs::user(Refs::IRA, 'agent-bouake'), User::class),
            $hier->setTime(12, 38));

        $this->journaliser($manager, $identreprise, ActiviteLogger::VOYAGE_CLOTURE,
            sprintf('Clôture du voyage %s au terminus de Korhogo', $v1->getCodevoyage()),
            'Voyage', $v1->getId(), $chefKorhogo, $hier->setTime(16, 40));

        // ---------------------------------------------------------- Billets : remise, annulation
        $t3 = $this->getReference(Refs::ticket(Refs::IRA, 't3'), Ticket::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::TICKET_REMISE,
            sprintf('Remise de 10 %% (1 500 F) sur le billet %s — bénéficiaire : Étudiants (convention UVCI)', $t3->getCodeticket()),
            'Ticket', $t3->getId(), $agentAbidjan, $hier->setTime(6, 35));

        $t5 = $this->getReference(Refs::ticket(Refs::IRA, 't5'), Ticket::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::TICKET_ANNULE,
            sprintf('Billet %s annulé avec remboursement — motif : client absent au départ', $t5->getCodeticket()),
            'Ticket', $t5->getId(), $agentAbidjan, $hier->setTime(6, 55));

        $t10 = $this->getReference(Refs::ticket(Refs::IRA, 't10'), Ticket::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::TICKET_REPORTE,
            sprintf('Billet %s reporté sur le départ du lendemain', $t10->getCodeticket()),
            'Ticket', $t10->getId(), $agentAbidjan, $v2->getDatedepartprevue()->modify('-20 minutes'));

        // ------------------------------------------------------ Bagage au montant forcé (audit)
        $b3 = $this->getReference('bagage-' . Refs::IRA . '-b3', Bagage::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::BAGAGE_MONTANT_FORCE,
            sprintf('Montant du bagage %s forcé à 7 000 F (grille : 5 000 F) — écart de 2 000 F', $b3->getCodebagage()),
            'Bagage', $b3->getId(), $agentYamoussoukro, $hier->setTime(9, 45));

        // ------------------------------------------------------------- Voyage en cours (à bord)
        $this->journaliser($manager, $identreprise, ActiviteLogger::VOYAGE_COMMERCIAL,
            sprintf('Commercial affecté au voyage %s : Diomandé Karim', $v2->getCodevoyage()),
            'Voyage', $v2->getId(), $exploitation, $v2->getDatedepartprevue()->modify('-2 hours'));

        $this->journaliser($manager, $identreprise, ActiviteLogger::VOYAGE_POSITION,
            sprintf('Position du voyage %s avancée à la gare de Bouaké', $v2->getCodevoyage()),
            'Voyage', $v2->getId(), $commercial, $maintenant->modify('-20 minutes'));

        // ------------------------------------------------------------------------ Réservations
        $r2 = $this->getReference(Refs::reservation(Refs::IRA, 'r2'), Reservation::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::RESERVATION_CONFIRMEE,
            sprintf('Réservation %s encaissée (Mobile Money) — 15 000 F', $r2->getCode()),
            'Reservation', $r2->getId(), $agentAbidjan, $maintenant->modify('-3 hours'));

        $this->journaliser($manager, $identreprise, ActiviteLogger::RESERVATION_BILLET_EMIS,
            sprintf('Billet émis à partir de la réservation %s', $r2->getCode()),
            'Reservation', $r2->getId(), $agentAbidjan, $maintenant->modify('-1 hour'));

        $r6 = $this->getReference(Refs::reservation(Refs::IRA, 'r6'), Reservation::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::RESERVATION_ANNULEE,
            sprintf('Réservation %s annulée — place rendue au voyage', $r6->getCode()),
            'Reservation', $r6->getId(), $agentAbidjan, $maintenant->modify('-4 hours'));

        // ---------------------------------------------------------------------------- Courrier
        $k5 = $this->getReference('courrier-' . Refs::IRA . '-k5', Courrier::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::COURRIER_ANNULE,
            sprintf('Courrier %s annulé avant embarquement à la demande de l\'expéditeur', $k5->getCodecourrier()),
            'Courrier', $k5->getId(), $agentAbidjan, $hier->setTime(15, 40));

        $k6 = $this->getReference('courrier-' . Refs::IRA . '-k6', Courrier::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::COLIS_PERDU,
            sprintf('Colis déclaré perdu sur le courrier %s : « Petit colis non retrouvé à l\'arrivée »', $k6->getCodecourrier()),
            'Courrier', $k6->getId(), $chefKorhogo, $hier->setTime(17, 50));

        // ----------------------------------------------------------------- Flotte et maintenance
        $d1 = $this->getReference('depannage-' . Refs::IRA . '-d1', Depannage::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::DEPANNAGE_OUVERT,
            'Dépannage ouvert sur le car 4521 AB 01 — système de freinage',
            'Depannage', $d1->getId(), $responsableFlotte, $jour->modify('-6 days')->setTime(8, 0));

        $this->journaliser($manager, $identreprise, ActiviteLogger::DEPANNAGE_CLOTURE,
            'Dépannage clôturé sur le car 4521 AB 01 — coût total 554 000 F',
            'Depannage', $d1->getId(), $responsableFlotte, $jour->modify('-5 days')->setTime(17, 30));

        $d2 = $this->getReference('depannage-' . Refs::IRA . '-d2', Depannage::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::DEPANNAGE_OUVERT,
            'Dépannage ouvert sur le car 1290 EF 01 — casse moteur en ligne',
            'Depannage', $d2->getId(), $responsableFlotte, $jour->modify('-6 days')->setTime(14, 30));

        $d3 = $this->getReference('depannage-' . Refs::IRA . '-d3', Depannage::class);
        $this->journaliser($manager, $identreprise, ActiviteLogger::DEPANNAGE_ANNULE,
            'Dépannage annulé sur le car 2211 IJ 01 — pièces remises en stock',
            'Depannage', $d3->getId(), $responsableFlotte, $jour->modify('-2 days')->setTime(11, 0));

        // ------------------------------------------------------------------------------- Stock
        // Le registre d'inventaire porte déjà qui/quoi/combien : seul le MOTIF est consigné ici.
        $this->journaliser($manager, $identreprise, ActiviteLogger::STOCK_AJUSTE,
            'Stock ajusté sur Filtre à air : -3 — motif : casse constatée lors du rangement du magasin',
            'Piece', $this->getReference(Refs::piece(Refs::IRA, 'filtre-air'), Piece::class)->getId(),
            $magasinier, $jour->modify('-2 days')->setTime(16, 20));

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    private function journaliser(
        ObjectManager $manager,
        int $identreprise,
        string $type,
        string $libelle,
        string $cibletype,
        ?int $cibleid,
        User $auteur,
        DateTimeImmutable $date
    ): void {
        $activite = (new Activite())
            ->setType($type)
            ->setLibelle($libelle)
            // Référence POLYMORPHE : le journal ne porte pas de clé étrangère, il pointe par type + id.
            ->setCibletype($cibletype)
            ->setCibleid($cibleid)
            ->setAuteur($auteur);
        $activite->setIdentreprise($identreprise);
        $activite->setCreatedBy($auteur->getId());

        $manager->persist($this->daterA($activite, $date));
    }
}

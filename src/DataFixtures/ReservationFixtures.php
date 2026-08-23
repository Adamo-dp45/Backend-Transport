<?php

namespace App\DataFixtures;

use App\Domain\Enum\ReservationStatus;
use App\Domain\Enum\TicketStatus;
use App\Entity\Client;
use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Reservation;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Les réservations, dans TOUS leurs états.
 *
 * Une réservation retient une PLACE (jamais un siège) et son échéance est calée sur l'heure de
 * passage du car À LA GARE DE MONTÉE — pas sur le départ du voyage. La réservation R7 le
 * matérialise : montée à Bouaké, donc échéance cinq heures après celle d'un passager d'Abidjan sur
 * le même départ.
 *
 * 'dateexpiration' porte l'échéance QUI S'APPLIQUE À L'INSTANT : celle du paiement tant que la
 * réservation est impayée, celle de la présentation dès l'encaissement. Les deux cas figurent ici.
 *
 * Seuls EN_ATTENTE et CONFIRMEE tiennent une place ('ReservationStatus::tenantsPlace'). Les états
 * EXPIREE, ANNULEE et A_REGULARISER sont donc présents pour vérifier qu'ils NE bloquent PAS la
 * capacité — une erreur à cet endroit ne se voit qu'au moment où un voyage refuse une vente sans
 * raison visible.
 */
class ReservationFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    use HorodatageTrait;

    /** Compteur du numéro de bon, remis à plat par entreprise comme le fait le service de création. */
    private int $compteur = 0;

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
        $agent = $this->getReference(Refs::user(Refs::IRA, 'agent-abidjan'), User::class);

        $maintenant = new DateTimeImmutable();
        $hier = new DateTimeImmutable('yesterday');
        $demain = new DateTimeImmutable('tomorrow');

        $v1 = $this->getReference(Refs::voyage(Refs::IRA, 'v1'), Voyage::class);
        $v3 = $this->getReference(Refs::voyage(Refs::IRA, 'v3'), Voyage::class);

        // Heures de passage sur V3 (départ 07:00 d'Abidjan) : Abidjan 07:00, Bouaké 12:00 (+5 h).
        // Limite de présentation d'IRA : 15 minutes avant le passage.
        $presentationAbidjan = $demain->setTime(6, 45);
        $presentationBouake = $demain->setTime(11, 45);

        // ------------------------------------------------- R1 · réservée, en attente de paiement
        // Impayée : c'est le délai de PAIEMENT (30 min depuis la création) qui court.
        $this->creerReservation($manager, $identreprise, 'r1', $v3, 'abidjan', 'korhogo', 15000,
            'c6', ReservationStatus::STATUT_EN_ATTENTE, 'EN_ATTENTE_PAIEMENT',
            $maintenant->modify('+20 minutes'), $maintenant->modify('-10 minutes'), source: 'MOBILE');

        // -------------------------------------------- R2 · payée en ligne, billet DÉJÀ retiré ---
        $r2 = $this->creerReservation($manager, $identreprise, 'r2', $v3, 'abidjan', 'korhogo', 15000,
            'c7', ReservationStatus::STATUT_CONFIRMEE, 'PAYE',
            // Encaissée : l'échéance bascule sur la limite de PRÉSENTATION.
            $presentationAbidjan, $maintenant->modify('-3 hours'), source: 'MOBILE',
            datepaiement: $maintenant->modify('-3 hours +4 minutes'),
            referencepaiement: 'MM-SIM-7F3A21C9');

        // Le bon a été honoré : un billet est émis et RATTACHÉ à la réservation. Ce lien est ce qui
        // évite le double comptage de la recette (reconnue au paiement, pas à l'émission).
        $billetR2 = (new Ticket())
            ->setVoyage($v3)
            ->setSiege($this->getReference(Refs::siege(Refs::IRA, 'car4', 2), Siege::class))
            ->setGare($this->getReference(Refs::gare(Refs::IRA, 'abidjan'), Gare::class))
            ->setGaredescente($this->getReference(Refs::gare(Refs::IRA, 'korhogo'), Gare::class))
            ->setPrix(15000)
            ->setStatut(TicketStatus::STATUT_VALIDE->value)
            // Deuxième billet émis sur V3 (le premier est le report 't11').
            ->setCodeticket(sprintf('%s-TCK-%s-2', $v3->getCodevoyage(), $demain->format('Y')))
            ->setReservation($r2)
            ->setIdentreprise($identreprise);
        $client = $this->getReference(Refs::client(Refs::IRA, 'c7'), Client::class);
        $billetR2
            ->setClient($client)
            ->setNomclient($client->getNom())
            ->setContactclient($client->getContact());
        $billetR2->setCreatedBy($agent->getId());
        $manager->persist($this->daterA($billetR2, $maintenant->modify('-1 hour')));
        $this->addReference(Refs::ticket(Refs::IRA, 'r2-billet'), $billetR2);

        $r2->setTicket($billetR2);

        // ------------------------------------------ R3 · payée au guichet, billet pas encore émis
        $this->creerReservation($manager, $identreprise, 'r3', $v3, 'abidjan', 'bouake', 8000,
            'c8', ReservationStatus::STATUT_CONFIRMEE, 'PAYE',
            $presentationAbidjan, $maintenant->modify('-5 hours'), source: 'GUICHET',
            datepaiement: $maintenant->modify('-5 hours'));

        // ------------------------------------------------------------ R4 · no-show à régulariser
        // Payée, mais le client ne s'est pas présenté avant le passage du car sur V1 (hier).
        // Elle ne tient plus de place ; elle reste récupérable par report + pénalité.
        $this->creerReservation($manager, $identreprise, 'r4', $v1, 'abidjan', 'korhogo', 15000,
            'c4', ReservationStatus::STATUT_A_REGULARISER, 'PAYE',
            $hier->setTime(6, 45), $hier->modify('-1 day')->setTime(18, 30), source: 'MOBILE',
            datepaiement: $hier->modify('-1 day')->setTime(18, 35),
            referencepaiement: 'MM-SIM-11B8E402');

        // ------------------------------------------------------------- R5 · expirée (jamais payée)
        // Bon expiré = aucun remboursement (politique maison) : il n'y avait rien à rembourser.
        $this->creerReservation($manager, $identreprise, 'r5', $v1, 'abidjan', 'yamoussoukro', 5000,
            null, ReservationStatus::STATUT_EXPIREE, 'EN_ATTENTE_PAIEMENT',
            $hier->modify('-1 day')->setTime(20, 15), $hier->modify('-1 day')->setTime(19, 45),
            source: 'MOBILE', nomClient: 'Visiteur mobile', contactClient: '+225 07 00 00 00 20');

        // ------------------------------------------------------------------- R6 · annulée (place rendue)
        $this->creerReservation($manager, $identreprise, 'r6', $v3, 'abidjan', 'korhogo', 15000,
            'c1', ReservationStatus::STATUT_ANNULEE, 'EN_ATTENTE_PAIEMENT',
            $presentationAbidjan, $maintenant->modify('-6 hours'), source: 'MOBILE');

        // -------------------------- R7 · montée à une gare INTERMÉDIAIRE (échéance décalée de 5 h)
        $this->creerReservation($manager, $identreprise, 'r7', $v3, 'bouake', 'korhogo', 8000,
            null, ReservationStatus::STATUT_EN_ATTENTE, 'EN_ATTENTE_PAIEMENT',
            // Échéance de paiement bornée par la présentation à BOUAKÉ, pas à Abidjan.
            $maintenant->modify('+25 minutes'), $maintenant->modify('-5 minutes'),
            source: 'MOBILE', nomClient: 'Ouattara Fanta', contactClient: '+225 07 00 00 00 21');

        // ================================================================== Seconde compagnie ==
        $sahelId = $this->getReference(Refs::entreprise(Refs::SAHEL), Entreprise::class)->getId();
        $vSahel = $this->getReference(Refs::voyage(Refs::SAHEL, 'v1'), Voyage::class);

        $reservationSahel = (new Reservation())
            ->setCode(sprintf('RES-%s-1', $demain->format('Y')))
            ->setVoyage($vSahel)
            ->setGare($this->getReference(Refs::gare(Refs::SAHEL, 'ferkessedougou'), Gare::class))
            ->setGaredescente($this->getReference(Refs::gare(Refs::SAHEL, 'ouangolodougou'), Gare::class))
            ->setPrix(3000)
            ->setStatut(ReservationStatus::STATUT_EN_ATTENTE->value)
            ->setEtatpaiement('EN_ATTENTE_PAIEMENT')
            ->setDateexpiration($maintenant->modify('+55 minutes'))
            ->setSource('MOBILE')
            ->setNomclient('Sekongo Tenena')
            ->setContactclient('+225 07 55 44 33 22')
            ->setClient($this->getReference(Refs::client(Refs::SAHEL, 'c1'), Client::class))
            ->setIdentreprise($sahelId);
        $manager->persist($this->daterA($reservationSahel, $maintenant->modify('-5 minutes')));
        $this->addReference(Refs::reservation(Refs::SAHEL, 'r1'), $reservationSahel);

        $manager->flush();
        $this->appliquerHorodatages($manager);
    }

    private function creerReservation(
        ObjectManager $manager,
        int $identreprise,
        string $code,
        Voyage $voyage,
        string $codeGareMontee,
        string $codeGareDescente,
        int $prix,
        ?string $codeClient,
        ReservationStatus $statut,
        string $etatpaiement,
        DateTimeImmutable $expiration,
        DateTimeImmutable $creation,
        string $source = 'GUICHET',
        ?DateTimeImmutable $datepaiement = null,
        ?string $referencepaiement = null,
        ?string $nomClient = null,
        ?string $contactClient = null
    ): Reservation {
        $reservation = new Reservation();
        $reservation
            ->setCode(sprintf('RES-%s-%d', $creation->format('Y'), ++$this->compteur))
            ->setVoyage($voyage)
            ->setGare($this->getReference(Refs::gare(Refs::IRA, $codeGareMontee), Gare::class))
            ->setGaredescente($this->getReference(Refs::gare(Refs::IRA, $codeGareDescente), Gare::class))
            // Prix VERROUILLÉ à la réservation : une évolution de la grille ne le change plus.
            ->setPrix($prix)
            ->setStatut($statut->value)
            ->setEtatpaiement($etatpaiement)
            ->setDateexpiration($expiration)
            ->setSource($source)
            ->setDatepaiement($datepaiement)
            ->setReferencepaiement($referencepaiement)
            ->setIdentreprise($identreprise);

        if ($codeClient !== null) {
            $client = $this->getReference(Refs::client(Refs::IRA, $codeClient), Client::class);
            $reservation
                ->setClient($client)
                ->setNomclient($client->getNom())
                ->setContactclient($client->getContact());
        } else {
            // Réservation en INVITÉ : pas de compte, l'identité est saisie dans l'app mobile.
            $reservation->setNomclient($nomClient)->setContactclient($contactClient);
        }

        $manager->persist($this->daterA($reservation, $creation));
        $this->addReference(Refs::reservation(Refs::IRA, $code), $reservation);

        return $reservation;
    }
}

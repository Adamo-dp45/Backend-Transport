<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Bagage;
use App\Entity\Reservation;
use App\Entity\Sessioncaisse;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LES SORTIES DE CAISSE et les encaissements de réservation (palier 3).
 *
 * Le cœur du palier tient en une phrase : la vente de lundi reste dans la caisse de lundi, le
 * remboursement de jeudi pèse sur celle de jeudi. Les confondre rouvrirait une caisse déjà signée,
 * et l'agent de jeudi aurait un manquant que rien n'expliquerait.
 */
final class RemboursementCaisseTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarifbagage($this->reseau->entreprise, poidsmin: 0, poidsmax: 30, montant: 2000);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            [
                'Ticket' => ['VOIR', 'CREER', 'DESISTER'],
                'Bagage' => ['VOIR', 'CREER', 'MODIFIER'],
                'Courrier' => ['VOIR', 'MODIFIER'],
                'Reservation' => ['VOIR', 'MODIFIER'],
                'Sessioncaisse' => ['VOIR', 'CREER', 'CLOTURER'],
            ]
        );
    }

    #[Test]
    #[TestDox('Vente et annulation le MÊME jour : le tiroir revient à son point de départ')]
    public function venteEtAnnulationLeMemeJour(): void
    {
        $this->ouvrir(fonds: 10000);
        $billet = $this->vendre(siege: 1);
        $this->annuler($billet);

        $this->cloturer(montantcompte: 10000);

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(15000, $session->getTotalbillets(), "l'argent EST entré à la vente");
        self::assertSame(15000, $session->getTotalremboursements(), 'et il est ressorti au remboursement');
        self::assertSame(
            10000,
            $session->getMontanttheorique(),
            'les deux mouvements se compensent : il ne reste que le fonds. Filtrer sur le statut '
            . 'aurait fait disparaître les deux, en masquant des mouvements réels'
        );
        self::assertSame(0, $session->getEcart());
    }

    #[Test]
    #[TestDox('Le remboursement emporte les bagages annulés en cascade')]
    public function remboursementAvecBagages(): void
    {
        $this->ouvrir();
        $billet = $this->vendre(siege: 1);
        $this->ajouterBagage($billet);

        $this->annuler($billet);
        $this->cloturer(montantcompte: 0);

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(2000, $session->getTotalbagages());
        self::assertSame(
            17000,
            $session->getTotalremboursements(),
            'le client repart avec son billet ET son bagage : au comptoir on rend tout en une fois'
        );
        self::assertSame(0, $session->getMontanttheorique());

        $bagage = $this->em->getRepository(Bagage::class)->findOneBy(['ticket' => $billet]);
        self::assertSame(2000, $bagage?->getMontantrembourse(), 'le bagage porte sa propre ligne de sortie');
    }

    #[Test]
    #[TestDox("Annulation APRÈS la clôture : la caisse de la vente ne bouge pas, celle du jour porte la sortie")]
    public function annulationApresCloture(): void
    {
        $this->ouvrir();
        $billet = $this->vendre(siege: 1);
        $this->cloturer(montantcompte: 15000);
        $this->assertStatut(200);
        $caisseDeLaVente = $this->caisse()->getId();

        // Le lendemain : le client se présente, l'agent rembourse. Sa caisse est fermée.
        $this->annuler($billet);

        $caisses = $this->em->getRepository(Sessioncaisse::class)->findBy(['agent' => $this->agent->getId()]);
        self::assertCount(2, $caisses, 'le remboursement a ouvert une NOUVELLE caisse');

        $ancienne = $this->em->getRepository(Sessioncaisse::class)->find($caisseDeLaVente);
        $this->em->refresh($ancienne);
        self::assertSame(15000, $ancienne->getMontanttheorique(), 'une caisse signée ne se rouvre pas');
        self::assertSame(0, $ancienne->getEcart());

        $this->cloturer(montantcompte: 0, motifecart: 'Remboursement sur le fonds du matin');
        $this->assertStatut(200);
        self::assertSame(
            15000,
            $this->caisse()->getTotalremboursements(),
            "c'est le tiroir d'AUJOURD'HUI qui s'est vidé"
        );
    }

    #[Test]
    #[TestDox("Un bon encaissé au GUICHET entre dans la caisse, un bon payé EN LIGNE n'y entre pas")]
    public function bonAuGuichetSeulementDansLaCaisse(): void
    {
        $this->ouvrir();

        $enLigne = $this->scenario->reservation(
            $this->reseau,
            $this->voyage,
            de: 'Abidjan',
            a: 'Korhogo',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            prix: 9000,
            etatpaiement: 'PAYE',
            datepaiement: new DateTimeImmutable('-1 hour')
        );

        $auGuichet = $this->scenario->reservation(
            $this->reseau,
            $this->voyage,
            de: 'Abidjan',
            a: 'Korhogo',
            prix: 12000
        );
        $this->requete('PATCH', '/api/reservations/' . $auGuichet->getId() . '/confirmer', $this->agent, []);
        $this->assertStatut(200);

        $this->cloturer(montantcompte: 12000);

        $this->assertStatut(200);
        self::assertSame(
            12000,
            $this->caisse()->getTotalreservations(),
            "seul le bon encaissé au comptoir a touché un tiroir ; l'argent du paiement mobile "
            . "n'en a jamais vu, il est arrivé sur un compte de l'entreprise"
        );
        self::assertNull(
            $this->em->getRepository(Reservation::class)->find($enLigne->getId())?->getSessioncaisse(),
            'le bon payé en ligne ne porte aucune caisse'
        );
    }

    #[Test]
    #[TestDox('La régularisation dépose sa pénalité et son complément dans la caisse du jour')]
    public function regularisationDansLaCaisseDuJour(): void
    {
        $this->ouvrir();

        /*
            Les montants sont posés directement : ce qui est éprouvé ici est le CALCUL du théorique
            sur les postes 6 et 7, pas le parcours de régularisation — il a ses propres tests, et le
            monter demanderait un no-show, un départ de report et une grille de pénalité.
        */
        $reservation = $this->scenario->reservation(
            $this->reseau,
            $this->voyage,
            de: 'Abidjan',
            a: 'Korhogo',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            prix: 10000,
            etatpaiement: 'PAYE'
        );
        $reservation
            ->setPenalitemontant(2000)
            ->setMontantcomplement(3000)
            ->setSessioncaisseregul($this->caisse());
        $this->em->flush();

        $this->cloturer(montantcompte: 5000);

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(2000, $session->getTotalpenalites());
        self::assertSame(3000, $session->getTotalcomplements());
        self::assertSame(
            0,
            $session->getTotalreservations(),
            "le prix du bon n'est PAS recompté : il a été encaissé ailleurs, à un autre moment"
        );
        self::assertSame(5000, $session->getMontanttheorique());
    }

    #[Test]
    #[TestDox('Un courrier repris avant le départ est remboursé, frais de suivi compris')]
    public function courrierRepris(): void
    {
        $this->ouvrir();

        $courrier = $this->scenario->courrier($this->reseau, 'Abidjan', 'Korhogo', montant: 5000, fraissuivi: 500);
        $courrier
            ->setCreatedBy($this->agent->getId())
            ->setSessioncaisse($this->caisse());
        $this->em->flush();

        $this->requete('PATCH', '/api/courriers/' . $courrier->getId() . '/annuler', $this->agent, []);
        $this->assertStatut(200);

        $this->cloturer(montantcompte: 0);

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(5000, $session->getTotalcourriers(), 'la taxe des colis');
        self::assertSame(500, $session->getTotalfraissuivi(), 'les frais de suivi, sur leur propre ligne');
        self::assertSame(
            5500,
            $session->getTotalremboursements(),
            'les deux repartent ensemble : le suivi SMS d\'un colis qui ne part pas n\'a pas plus '
            . 'lieu d\'être que son transport'
        );
        self::assertSame(0, $session->getMontanttheorique());
    }

    private function ouvrir(int $fonds = 0): void
    {
        $this->requete('POST', '/api/sessioncaisses', $this->agent, ['fondsouverture' => $fonds]);
        $this->assertStatut(201);
    }

    private function cloturer(int $montantcompte, ?string $motifecart = null): void
    {
        $corps = ['montantcompte' => $montantcompte];
        if ($motifecart !== null) {
            $corps['motifecart'] = $motifecart;
        }

        $this->requete(
            'PATCH',
            '/api/sessioncaisses/' . $this->caisse()->getId() . '/cloturer',
            $this->agent,
            $corps
        );
    }

    private function vendre(int $siege): int
    {
        $this->requete('POST', '/api/tickets', $this->agent, [
            'voyage' => '/api/voyages/' . $this->voyage->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($this->voyage, $siege)->getId(),
            'gare' => '/api/gares/' . $this->reseau->gare('Abidjan')->getId(),
            'garedescente' => '/api/gares/' . $this->reseau->gare('Korhogo')->getId(),
            'nomclient' => 'Client de test',
            'contactclient' => '+225 07 00 00 00 01',
        ]);
        $this->assertStatut(201);

        return $this->reponseJson()['id'];
    }

    private function ajouterBagage(int $billet): void
    {
        $this->requete('POST', '/api/bagages', $this->agent, [
            'ticket' => $billet,
            'nature' => 'Valise',
            'type' => 'LEGER',
            'poids' => 12,
        ]);
        $this->assertStatut(201);
    }

    private function annuler(int $billet): void
    {
        $this->requete('PATCH', '/api/tickets/' . $billet . '/desister', $this->agent, [
            'mode' => 'ANNULATION',
            'motif' => 'Le client ne part plus',
        ]);
        $this->assertStatut(200);

        $ticket = $this->em->getRepository(Ticket::class)->find($billet);
        self::assertNotNull($ticket?->getMontantrembourse(), 'le remboursement est chiffré');
    }

    /** La caisse OUVERTE de l'agent, ou la plus récente s'il n'en a plus d'ouverte. */
    private function caisse(): Sessioncaisse
    {
        $depot = $this->em->getRepository(Sessioncaisse::class);
        $session = $depot->findOneBy(['agent' => $this->agent->getId(), 'statut' => 'OUVERTE'])
            ?? $depot->findOneBy(['agent' => $this->agent->getId()], ['id' => 'DESC']);
        self::assertNotNull($session, 'aucune caisse pour cet agent');

        return $session;
    }
}

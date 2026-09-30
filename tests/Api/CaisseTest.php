<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Bagage;
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
 * LE RATTACHEMENT d'un encaissement à la caisse de l'agent (palier 1 du chantier Caisse).
 *
 * Ce qui se joue ici n'est pas « la session existe-t-elle » mais « le bon argent tombe-t-il dans le
 * bon tiroir » : une caisse qui ramasse ce qu'elle ne devrait pas rendrait un agent comptable d'une
 * vente qu'il n'a pas encaissée, et l'écart du soir désignerait un innocent.
 *
 * Les trois exclusions testées ici valent RÈGLE et non optimisation : le commercial à bord n'a pas
 * de guichet, le billet émis depuis un bon a vu son argent entrer à la réservation, et un acteur
 * sans gare n'a pas de tiroir. Chacune passe par un chemin de code différent — d'où un test par cas.
 */
final class CaisseTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarif($this->reseau, 'Bouaké', 'Korhogo', 8000);
        $this->scenario->tarifbagage($this->reseau->entreprise, poidsmin: 0, poidsmax: 30, montant: 2000);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'CREER'], 'Bagage' => ['VOIR', 'CREER'], 'Reservation' => ['VOIR', 'MODIFIER']]
        );
    }

    #[Test]
    #[TestDox("La première vente ouvre la caisse de l'agent, fonds à zéro")]
    public function premiereVenteOuvreLaCaisse(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(201);

        $session = $this->caisseDe($this->agent);
        self::assertNotNull($session, 'une vente au guichet doit tomber dans une caisse');
        self::assertTrue($session->isOuvertureautomatique(), "l'agent n'a pas ouvert sa caisse : sa vente l'a fait");
        self::assertSame(0, $session->getFondsouverture(), 'aucun fonds avancé sur une ouverture automatique');
        self::assertSame(
            $this->reseau->gare('Abidjan')->getId(),
            $session->getGare()?->getId(),
            'la caisse est celle de la gare où il tient son guichet'
        );
    }

    #[Test]
    #[TestDox('Deux ventes du même agent tombent dans UNE SEULE caisse')]
    public function deuxVentesUneSeuleCaisse(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));
        $this->assertStatut(201);
        $premier = $this->reponseJson()['id'];

        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 2, a: 'Korhogo'));
        $this->assertStatut(201);
        $second = $this->reponseJson()['id'];

        self::assertCount(
            1,
            $this->em->getRepository(Sessioncaisse::class)->findBy(['agent' => $this->agent->getId()]),
            "une seconde vente rejoint la caisse ouverte, elle n'en ouvre pas une autre"
        );
        self::assertSame(
            $this->sessionDuBillet($premier),
            $this->sessionDuBillet($second),
            'les deux billets pointent la même caisse'
        );
    }

    #[Test]
    #[TestDox("Un bagage rejoint la caisse de l'agent qui l'enregistre")]
    public function bagageRejointLaCaisse(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));
        $this->assertStatut(201);
        $billet = $this->reponseJson()['id'];

        $this->requete('POST', '/api/bagages', $this->agent, [
            'ticket' => $billet,
            'nature' => 'Valise',
            'type' => 'LEGER',
            'poids' => 12,
        ]);

        $this->assertStatut(201);
        $bagage = $this->em->getRepository(Bagage::class)->find($this->reponseJson()['id']);
        self::assertNotNull($bagage?->getSessioncaisse(), 'le bagage est encaissé au même guichet que le billet');
        self::assertSame(
            $this->sessionDuBillet($billet),
            $bagage->getSessioncaisse()?->getId(),
            'billet et bagage tombent dans la MÊME caisse : la session est mémoïsée sur la requête'
        );
    }

    #[Test]
    #[TestDox("La vente du commercial à bord n'ouvre AUCUNE caisse")]
    public function venteDuCommercialSansCaisse(): void
    {
        $commercial = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['CREER']]
        );
        $this->voyage
            ->setCommercial($commercial)
            ->setDatedepartreelle(new DateTimeImmutable('-2 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $this->requete('POST', '/api/tickets', $commercial, $this->payload(siege: 2, de: 'Bouaké', a: 'Korhogo'));

        $this->assertStatut(201);
        self::assertNull(
            $this->sessionDuBillet($this->reponseJson()['id']),
            "le vendeur à bord n'a pas de guichet : sa remise d'espèces se traitera comme un versement"
        );
        self::assertNull(
            $this->caisseDe($commercial),
            "et sa vente ne doit pas non plus lui ouvrir une caisse à sa gare d'attache"
        );
    }

    #[Test]
    #[TestDox("Le billet émis depuis un bon payé n'a pas de caisse : l'argent est entré à la réservation")]
    public function billetDeReservationSansCaisse(): void
    {
        $reservation = $this->scenario->reservation(
            $this->reseau,
            $this->voyage,
            de: 'Abidjan',
            a: 'Korhogo',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            etatpaiement: 'PAYE',
            datepaiement: new DateTimeImmutable('-1 hour')
        );

        $this->requete('PATCH', '/api/reservations/' . $reservation->getId() . '/emettre-billet', $this->agent, []);

        $this->assertStatut(200);

        $billet = $this->em->getRepository(Ticket::class)->findOneBy(['reservation' => $reservation->getId()]);
        self::assertNotNull($billet, 'le bon a bien produit un billet');
        self::assertNull(
            $billet->getSessioncaisse(),
            'honorer un bon ne fait entrer aucun argent au guichet : le compter ici le compterait deux fois'
        );
        self::assertNull($this->caisseDe($this->agent), "et n'ouvre donc aucune caisse");
    }

    #[Test]
    #[TestDox('Un acteur SANS GARE (admin, central) vend hors caisse')]
    public function acteurSansGareSansCaisse(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, gare: null, roles: ['ROLE_ADMIN']);

        $this->requete('POST', '/api/tickets', $admin, $this->payload(siege: 3, a: 'Korhogo'));

        $this->assertStatut(201);
        self::assertNull(
            $this->sessionDuBillet($this->reponseJson()['id']),
            "un acteur sans gare n'a pas de tiroir : lui ouvrir une caisse créerait un compte que personne ne viendrait fermer"
        );
    }

    private function caisseDe(User $user): ?Sessioncaisse
    {
        return $this->em->getRepository(Sessioncaisse::class)->findOneBy(['agent' => $user->getId()]);
    }

    private function sessionDuBillet(int $id): ?int
    {
        return $this->em->getRepository(Ticket::class)->find($id)?->getSessioncaisse()?->getId();
    }

    /** @return array<string, mixed> */
    private function payload(int $siege, string $a, string $de = 'Abidjan'): array
    {
        return [
            'voyage' => '/api/voyages/' . $this->voyage->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($this->voyage, $siege)->getId(),
            'gare' => '/api/gares/' . $this->reseau->gare($de)->getId(),
            'garedescente' => '/api/gares/' . $this->reseau->gare($a)->getId(),
            'nomclient' => 'Client de test',
            'contactclient' => '+225 07 00 00 00 01',
        ];
    }
}

<?php

namespace App\Tests\Api;

use App\Entity\Gare;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'ÉMISSION d'un billet (`POST /api/tickets`) — le flux d'écriture le plus chargé en règles.
 *
 * Le processor enchaîne huit gardes avant d'écrire quoi que ce soit : permission, gare de vente
 * imposée, position du car, cohérence du tronçon, provenance effective, grille tarifaire, plafond
 * de remise, siège libre. Un test par garde : ce sont autant de façons d'encaisser un client à qui
 * l'on ne pourra rien fournir, ou de refuser une vente légitime au guichet.
 */
final class VenteTicketTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Bouaké', 8000);
        $this->scenario->tarif($this->reseau, 'Bouaké', 'Korhogo', 8000);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'CREER']]
        );
    }

    #[Test]
    #[TestDox('Une vente au guichet crée le billet au tarif de la grille')]
    public function venteNominale(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(201);
        $reponse = $this->reponseJson();
        self::assertSame(15000, $reponse['prix'], 'le prix vient de la grille, pas du client');
        self::assertNotEmpty($reponse['codeticket'] ?? null);
    }

    #[Test]
    #[TestDox("Sans la permission CREER, la vente est refusée")]
    public function sansPermission(): void
    {
        $simpleAgent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        $this->requete('POST', '/api/tickets', $simpleAgent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox("Un agent ne vend qu'au départ de SA gare")]
    public function venteDepuisUneAutreGareRefusee(): void
    {
        // L'agent est à Abidjan mais tente d'émettre un billet au départ de Bouaké.
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, de: 'Bouaké', a: 'Korhogo'));

        $this->assertStatut(400);
        self::assertStringContainsString('au départ de votre gare', $this->messageErreur());
    }

    #[Test]
    #[TestDox('La descente doit être située après la montée')]
    public function tronconInverseRefuse(): void
    {
        $agentBouake = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            ['Ticket' => ['CREER']]
        );

        $this->requete('POST', '/api/tickets', $agentBouake, $this->payload(siege: 1, de: 'Bouaké', a: 'Abidjan'));

        $this->assertStatut(400);
        self::assertStringContainsString('après la gare de montée', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Un trajet absent de la grille tarifaire est refusé plutôt que vendu à zéro")]
    public function trajetSansTarif(): void
    {
        // Aucun tarif Abidjan → Bouaké sur cette compagnie-ci.
        $autre = $this->scenario->reseau(['Nord', 'Centre', 'Sud'], [null, 120, 120]);
        $voyage = $this->scenario->voyage($autre, car: $this->scenario->car($autre->entreprise, 10));
        $agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($autre->entreprise, gare: $autre->gare('Nord')),
            ['Ticket' => ['CREER']]
        );

        $this->requete('POST', '/api/tickets', $agent, [
            'voyage' => '/api/voyages/' . $voyage->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($voyage, 1)->getId(),
            'gare' => '/api/gares/' . $autre->gare('Nord')->getId(),
            'garedescente' => '/api/gares/' . $autre->gare('Sud')->getId(),
            'nomclient' => 'Client sans tarif',
            'contactclient' => '+225 07 00 00 00 00',
        ]);

        $this->assertStatut(400);
        self::assertStringContainsString('Aucun tarif défini', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Le car ayant quitté la gare de montée, la vente est fermée")]
    public function venteApresDepartDuCar(): void
    {
        $this->voyage
            ->setDatedepartreelle(new DateTimeImmutable('-1 hour'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(400);
        self::assertStringContainsString('a déjà quitté', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Sur un départ partiel, aucune vente avant la gare de provenance")]
    public function venteAvantLaProvenanceEffective(): void
    {
        $partiel = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours'),
            provenance: 'Bouaké'
        );

        $this->requete('POST', '/api/tickets', $this->agent, [
            'voyage' => '/api/voyages/' . $partiel->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($partiel, 1)->getId(),
            'gare' => '/api/gares/' . $this->reseau->gare('Abidjan')->getId(),
            'garedescente' => '/api/gares/' . $this->reseau->gare('Korhogo')->getId(),
            'nomclient' => 'Client amont',
            'contactclient' => '+225 07 00 00 00 00',
        ]);

        $this->assertStatut(400);
        self::assertStringContainsString('aucune vente possible avant cette gare', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Un siège déjà occupé sur le tronçon ne peut pas être vendu deux fois")]
    public function siegeDejaOccupe(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));
        $this->assertStatut(201);

        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(400);
    }

    #[Test]
    #[TestDox("Une remise au-delà du plafond de la compagnie est refusée")]
    public function remiseAuDelaDuPlafond(): void
    {
        // Plafond à 20 % : une remise de 50 % relève de l'abus, pas du geste commercial.
        $this->scenario->configRemise($this->reseau->entreprise, maxPourcentage: 20);
        $beneficiaire = $this->scenario->beneficiaire($this->reseau->entreprise);

        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo') + [
            'remisetype' => 'POURCENTAGE',
            'remisevaleur' => 50,
            'beneficiaire' => '/api/beneficiaires/' . $beneficiaire->getId(),
        ]);

        $this->assertStatut(400);
        self::assertStringContainsString('plafond', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Une remise dans le plafond est appliquée et déduite du prix")]
    public function remiseAcceptee(): void
    {
        $this->scenario->configRemise($this->reseau->entreprise, maxPourcentage: 20);
        $beneficiaire = $this->scenario->beneficiaire($this->reseau->entreprise);

        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo') + [
            'remisetype' => 'POURCENTAGE',
            'remisevaleur' => 10,
            'beneficiaire' => '/api/beneficiaires/' . $beneficiaire->getId(),
        ]);

        $this->assertStatut(201);
        $reponse = $this->reponseJson();
        self::assertSame(1500, $reponse['remise'], '10 % de 15 000');
        self::assertSame(13500, $reponse['prix'], 'le prix payé est le tarif MOINS la remise');
    }

    #[Test]
    #[TestDox("Le billet vendu au guichet ne porte pas de commercial : la recette va à la gare")]
    public function venteGuichetSansCommercial(): void
    {
        $this->requete('POST', '/api/tickets', $this->agent, $this->payload(siege: 1, a: 'Korhogo'));

        $this->assertStatut(201);
        $ticket = $this->em->getRepository(Ticket::class)->find($this->reponseJson()['id']);
        self::assertNull($ticket?->getCommercial(), 'le canal « commercial » est réservé à la vente à bord');
    }

    #[Test]
    #[TestDox("Le commercial du voyage vend depuis la position du car, pas depuis sa gare d'attache")]
    public function venteABordDepuisLaPositionDuCar(): void
    {
        $commercial = $this->scenario->autoriser(
            // Rattaché à Abidjan, mais le car est à Bouaké.
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
        $ticket = $this->em->getRepository(Ticket::class)->find($this->reponseJson()['id']);
        self::assertSame(
            $commercial->getId(),
            $ticket?->getCommercial()?->getId(),
            'la vente à bord est tracée : sa recette revient à la gare d\'affectation du commercial'
        );
    }

    /** @return array<string, mixed> */
    private function payload(int $siege, string $a, string $de = 'Abidjan', ?Voyage $voyage = null): array
    {
        $voyage ??= $this->voyage;

        return [
            'voyage' => '/api/voyages/' . $voyage->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($voyage, $siege)->getId(),
            'gare' => '/api/gares/' . $this->reseau->gare($de)->getId(),
            'garedescente' => '/api/gares/' . $this->reseau->gare($a)->getId(),
            'nomclient' => 'Client de test',
            'contactclient' => '+225 07 00 00 00 01',
        ];
    }

    private function messageErreur(): string
    {
        $r = $this->reponseJson();

        return (string) ($r['detail'] ?? $r['description'] ?? $r['hydra:description'] ?? '');
    }
}

<?php

namespace App\Tests\Api;

use App\Entity\Reservation;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * La CRÉATION d'une réservation par un client invité (`POST /api/reservation/reservations`).
 *
 * C'est la seule ÉCRITURE ouverte sans authentification de toute l'application : le périmètre
 * entreprise n'y tient qu'au paramètre `?slug=`, et rien d'autre ne protège la compagnie. Le
 * serveur doit donc tout décider lui-même — le prix (jamais celui annoncé par le client), la
 * capacité, l'échéance de paiement — et refuser les tronçons qu'il ne desservira pas.
 */
final class ReservationPubliqueEcritureTest extends ApiTestCase
{
    private Reseau $compagnie;

    private Reseau $concurrente;

    private int $voyageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compagnie = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->compagnie, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarif($this->compagnie, 'Bouaké', 'Korhogo', 8000);
        $this->scenario->parametreReservation($this->compagnie->entreprise, presentationMinutes: 15, paiementMinutes: 30);

        $this->voyageId = (int) $this->scenario->voyage(
            $this->compagnie,
            car: $this->scenario->car($this->compagnie->entreprise, 3),
            depart: new DateTimeImmutable('+6 hours')
        )->getId();

        $this->concurrente = $this->scenario->reseau(['Daloa', 'Man'], [null, 120]);
    }

    #[Test]
    #[TestDox("Un invité réserve sans aucun jeton, au prix de la grille")]
    public function reservationNominale(): void
    {
        $this->reserver($this->compagnie, 'Abidjan', 'Korhogo');

        $this->assertStatut(201);
        $reponse = $this->reponseJson();
        // Le DTO public parle de « montant », et n'expose AUCUN identifiant interne : un invité
        // n'a pas à connaître les clés de la base, il ne manipule que son code de réservation.
        self::assertSame(15000, $reponse['montant'] ?? null, 'le prix vient du serveur, jamais du client');
        self::assertNotEmpty($reponse['code'] ?? null, 'le code est le « bon » que le client présentera');
    }

    #[Test]
    #[TestDox("L'échéance de paiement est posée par le serveur")]
    public function echeancePosee(): void
    {
        $this->reserver($this->compagnie, 'Abidjan', 'Korhogo');
        $this->assertStatut(201);

        $reponse = $this->reponseJson();
        self::assertSame('EN_ATTENTE_PAIEMENT', $reponse['etatpaiement'] ?? null);
        self::assertNotEmpty($reponse['dateexpiration'] ?? null, 'le client doit savoir jusqu\'à quand payer');

        // Le bon ne doit pas naître déjà échu : ce serait inutilisable.
        $reservation = $this->em->getRepository(Reservation::class)->findOneBy(['code' => $reponse['code']]);
        self::assertNotNull($reservation);
        self::assertGreaterThan(new DateTimeImmutable(), $reservation->getDateexpiration());
    }

    #[Test]
    #[TestDox("Une réservation faite sur le slug d'une autre compagnie est refusée")]
    public function voyageDUneAutreCompagnie(): void
    {
        // Slug de la concurrente, identifiant de voyage de la première : le croisement doit échouer.
        $this->requete('POST', '/api/reservation/reservations?slug=' . $this->concurrente->entreprise->getSlug(), corps: [
            'nom' => 'Client curieux',
            'contact' => '+225 07 00 00 00 01',
            'voyage' => $this->voyageId,
            'montee' => $this->compagnie->gare('Abidjan')->getId(),
            'descente' => $this->compagnie->gare('Korhogo')->getId(),
        ]);

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->compterReservations(), 'rien ne doit avoir été écrit');
    }

    #[Test]
    #[TestDox("Un slug inconnu ne crée rien")]
    public function slugInconnu(): void
    {
        $this->requete('POST', '/api/reservation/reservations?slug=compagnie-fantome', corps: [
            'nom' => 'Client',
            'contact' => '+225 07 00 00 00 01',
            'voyage' => $this->voyageId,
            'montee' => $this->compagnie->gare('Abidjan')->getId(),
            'descente' => $this->compagnie->gare('Korhogo')->getId(),
        ]);

        $this->assertStatut(404);
        self::assertSame(0, $this->compterReservations());
    }

    #[Test]
    #[TestDox("La descente doit suivre la montée")]
    public function tronconInverse(): void
    {
        $this->reserver($this->compagnie, 'Korhogo', 'Abidjan');

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
        self::assertSame(0, $this->compterReservations());
    }

    #[Test]
    #[TestDox("Une charge incomplète est rejetée par la validation")]
    public function chargeIncomplete(): void
    {
        $this->requete('POST', '/api/reservation/reservations?slug=' . $this->compagnie->entreprise->getSlug(), corps: [
            'nom' => 'A',
        ]);

        $this->assertStatut(422);
    }

    #[Test]
    #[TestDox("Le car étant plein, la réservation est refusée plutôt qu'encaissée")]
    public function capaciteEpuisee(): void
    {
        // Trois places, trois billets déjà émis.
        $voyage = $this->relire(\App\Entity\Voyage::class, $this->voyageId);
        for ($siege = 1; $siege <= 3; $siege++) {
            $this->scenario->billet($this->compagnie, $voyage, siege: $siege, de: 'Abidjan', a: 'Korhogo');
        }

        $this->reserver($this->compagnie, 'Abidjan', 'Korhogo');

        self::assertGreaterThanOrEqual(
            400,
            $this->client->getResponse()->getStatusCode(),
            'encaisser sans pouvoir émettre de billet est précisément ce qu\'il faut éviter'
        );
    }

    #[Test]
    #[TestDox("Une fois le car parti de la gare de montée, on ne réserve plus")]
    public function carDejaParti(): void
    {
        $voyage = $this->relire(\App\Entity\Voyage::class, $this->voyageId);
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-1 hour'))
            ->setGarecourante($this->compagnie->gare('Bouaké'));
        $this->em->flush();

        $this->reserver($this->compagnie, 'Abidjan', 'Korhogo');

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox("Une montée en aval reste réservable pendant que le car roule")]
    public function monteeEnAvalEncoreReservable(): void
    {
        $voyage = $this->relire(\App\Entity\Voyage::class, $this->voyageId);
        $voyage->setDatedepartreelle(new DateTimeImmutable('-1 hour'));
        $this->em->flush();

        // Le car a quitté Abidjan mais n'est pas encore à Bouaké : il va y chercher ce client.
        $this->reserver($this->compagnie, 'Bouaké', 'Korhogo');

        $this->assertStatut(201);
        self::assertSame(8000, $this->reponseJson()['montant'] ?? null, 'tarif du tronçon Bouaké → Korhogo');
    }

    private function reserver(Reseau $reseau, string $de, string $a): void
    {
        $this->requete('POST', '/api/reservation/reservations?slug=' . $reseau->entreprise->getSlug(), corps: [
            'nom' => 'Client invité',
            'contact' => '+225 07 00 00 00 01',
            'voyage' => $this->voyageId,
            'montee' => $reseau->gare($de)->getId(),
            'descente' => $reseau->gare($a)->getId(),
        ]);
    }

    private function compterReservations(): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(r.id) FROM ' . Reservation::class . ' r')
            ->getSingleScalarResult();
    }
}

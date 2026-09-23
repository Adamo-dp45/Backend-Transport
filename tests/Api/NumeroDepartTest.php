<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le NUMÉRO DE DÉPART DU JOUR — « DÉPART 4 », la case que le passager lit sur son billet face à son
 * siège.
 *
 * Ce numéro s'IMPRIME, et c'est tout ce qui fait sa difficulté : une fois le billet remis, il ne
 * doit plus jamais désigner autre chose. On vérifie donc les deux faces de la règle — la suite se
 * remet à 1 chaque jour et pour chaque gare, et rien de ce qui arrive ensuite au voyage (une
 * création plus tardive, un décalage d'horaire, une mise en corbeille) ne redistribue un numéro
 * déjà attribué.
 *
 * Testé à travers HTTP plutôt que sur le service : l'attribution vit dans le processor, sous verrou
 * et dans la transaction d'écriture — c'est cet assemblage qui doit tenir, pas le calcul seul.
 */
final class NumeroDepartTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $abidjan;

    private User $bouake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);

        // Chaque gare lance ses propres départs : Abidjan depuis l'origine, Bouaké en départ partiel.
        $this->abidjan = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan'),
            roles: ['ROLE_ADMIN_GARE']
        );
        $this->bouake = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Bouaké'),
            roles: ['ROLE_ADMIN_GARE']
        );
    }

    #[Test]
    #[TestDox('Les départs du jour se suivent, et le lendemain repart à 1')]
    public function suiteDuJour(): void
    {
        self::assertSame(1, $this->creer($this->abidjan, 'tomorrow 07:00')['numerodepart']);
        self::assertSame(2, $this->creer($this->abidjan, 'tomorrow 12:00')['numerodepart']);

        // Le compteur est celui d'une JOURNÉE : au guichet, « départ 2 » ne veut rien dire sans elle.
        self::assertSame(1, $this->creer($this->abidjan, '+2 days 07:00')['numerodepart']);
    }

    #[Test]
    #[TestDox('Une gare qui lance un départ partiel tient sa propre suite')]
    public function suitePropreAChaqueGare(): void
    {
        $this->creer($this->abidjan, 'tomorrow 07:00');
        $this->creer($this->abidjan, 'tomorrow 12:00');

        /*
            Bouaké a de quoi remplir un car pour Korhogo et le lance elle-même : c'est le PREMIER
            départ de SA journée, quand bien même Abidjan en a déjà fait partir deux sur la même
            ligne. Le passager de Bouaké entend l'ordre des cars que SA gare fait partir — et son
            billet porte déjà sa destination.
        */
        self::assertSame(1, $this->creer($this->bouake, 'tomorrow 14:00')['numerodepart']);
    }

    #[Test]
    #[TestDox('Un départ ouvert après coup pour une heure plus tôt ne renumérote pas les autres')]
    public function numeroFigeALaCreation(): void
    {
        $sept = $this->creer($this->abidjan, 'tomorrow 07:00');

        // Le car de 5 h est décidé APRÈS : il part le premier, il porte pourtant le numéro suivant.
        $cinq = $this->creer($this->abidjan, 'tomorrow 05:00');

        self::assertSame(1, $sept['numerodepart']);
        self::assertSame(2, $cinq['numerodepart'], 'le numéro suit l\'ordre d\'OUVERTURE, pas celui des heures');
        self::assertSame(
            1,
            $this->relire(Voyage::class, (int) $sept['id'])->getNumerodepart(),
            'des billets portent déjà « Départ 1 » : ce numéro ne bouge plus'
        );
    }

    #[Test]
    #[TestDox("Décaler l'heure dans la même journée ne touche pas au numéro")]
    public function decalageDansLaJournee(): void
    {
        $voyage = $this->creer($this->abidjan, 'tomorrow 07:00');

        $this->replanifier((int) $voyage['id'], 'tomorrow 09:30');
        $this->assertStatut(200);

        self::assertSame(
            1,
            $this->relire(Voyage::class, (int) $voyage['id'])->getNumerodepart(),
            'un retard annoncé n\'est pas un autre départ'
        );
    }

    #[Test]
    #[TestDox('Un départ replanifié à un autre jour reprend un numéro dans sa journée d\'accueil')]
    public function replanificationVersUnAutreJour(): void
    {
        $veille = $this->creer($this->abidjan, 'tomorrow 07:00');
        $this->creer($this->abidjan, '+2 days 07:00');

        // Le car du jour J est reporté au lendemain, où un départ est déjà ouvert : garder « 1 »
        // ferait deux « Départ 1 » ce jour-là — et l'index unique refuserait l'écriture.
        $this->replanifier((int) $veille['id'], '+2 days 15:00');
        $this->assertStatut(200);

        self::assertSame(
            2,
            $this->relire(Voyage::class, (int) $veille['id'])->getNumerodepart(),
            'il prend le rang suivant de sa nouvelle journée'
        );
    }

    #[Test]
    #[TestDox('Un départ mis en corbeille ne rend pas son numéro')]
    public function corbeilleNeRendPasLeNumero(): void
    {
        $this->creer($this->abidjan, 'tomorrow 07:00');
        $annule = $this->creer($this->abidjan, 'tomorrow 12:00');

        $this->requete('PATCH', sprintf('/api/voyages/%d/remove', $annule['id']), $this->abidjan, []);
        $this->assertStatut(200);

        /*
            Le voyage supprimé garde sa ligne en base, donc son numéro et sa part de l'index unique.
            Rendre « 2 » au suivant, c'était au mieux un refus brut à l'insertion, au pire deux
            billets « Départ 2 » pour la même journée — le défaut corrigé sur 'codeticket', dont le
            générateur comptait les enregistrements non supprimés.
        */
        self::assertSame(3, $this->creer($this->abidjan, 'tomorrow 16:00')['numerodepart']);
    }

    /**
     * Ouvre un départ et rend la charge renvoyée par l'API.
     *
     * @return array<string, mixed>
     */
    private function creer(User $agent, string $depart): array
    {
        $this->requete('POST', '/api/voyages', $agent, [
            'ligne' => '/api/lignes/' . $this->reseau->ligne->getId(),
            'datedepartprevue' => (new DateTimeImmutable($depart))->format(DATE_ATOM),
        ]);
        $this->assertStatut(201);

        return $this->reponseJson();
    }

    private function replanifier(int $voyageId, string $depart): void
    {
        $this->requete('PATCH', '/api/voyages/' . $voyageId, $this->abidjan, [
            'datedepartprevue' => (new DateTimeImmutable($depart))->format(DATE_ATOM),
        ]);
    }
}

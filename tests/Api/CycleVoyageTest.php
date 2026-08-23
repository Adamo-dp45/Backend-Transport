<?php

namespace App\Tests\Api;

use App\Entity\Bagage;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le cycle d'exploitation d'un départ : démarrer · réceptionner · repartir · clôturer.
 *
 * « L'ORIGINE prépare · l'INTERMÉDIAIRE réceptionne · le TERMINUS clôture » n'est pas qu'une règle
 * d'affichage : chaque étape est une écriture gardée, et elle horodate un passage dont dépendent
 * ensuite les retards, la ponctualité et la fermeture des réservations. Les tests de service
 * vérifient les gardes ; ceux-ci vérifient qu'elles tiennent à travers HTTP, avec les permissions
 * réelles, et que l'état écrit en base est le bon.
 *
 * TOUT est construit dans setUp, avant la première requête : le client de test réinitialise les
 * services entre deux appels, ce qui détache les entités créées entre-temps (cf. {@see relire}).
 */
final class CycleVoyageTest extends ApiTestCase
{
    private Reseau $reseau;

    private int $voyageId;

    /** @var array<string, User> */
    private array $agents = [];

    private int $reservationAmontId;

    private int $reservationAvalId;

    private int $bagageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);

        $voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 20),
            depart: new DateTimeImmutable('-30 minutes')
        );
        $this->voyageId = (int) $voyage->getId();

        foreach (['Abidjan', 'Bouaké', 'Korhogo'] as $gare) {
            $this->agents[$gare] = $this->scenario->utilisateur(
                $this->reseau->entreprise,
                gare: $this->reseau->gare($gare),
                roles: ['ROLE_ADMIN_GARE']
            );
        }

        $this->reservationAmontId = (int) $this->scenario
            ->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo')->getId();
        $this->reservationAvalId = (int) $this->scenario
            ->reservation($this->reseau, $voyage, 'Bouaké', 'Korhogo')->getId();

        $billet = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');
        $this->bagageId = (int) $this->scenario->bagage($this->reseau, $billet, statut: 'EMBARQUE')->getId();
    }

    #[Test]
    #[TestDox("La gare d'origine démarre le voyage, ce qui horodate son départ réel")]
    public function demarrage(): void
    {
        $this->action('demarrer', 'Abidjan');

        $this->assertStatut(200);
        self::assertNotNull(
            $this->relire(Voyage::class, $this->voyageId)->getDatedepartreelle(),
            'le départ réel fonde tous les calculs de retard'
        );
    }

    #[Test]
    #[TestDox("Une gare intermédiaire réceptionne le voyage et fait avancer la position du car")]
    public function reception(): void
    {
        $this->demarrer();

        $this->action('receptionner', 'Bouaké');

        $this->assertStatut(200);
        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->relire(Voyage::class, $this->voyageId)->getGarecourante()?->getId(),
            'la position courante suit la réception : c\'est elle qui ferme la vente en amont'
        );
    }

    #[Test]
    #[TestDox("Ni la gare de départ ni le terminus ne réceptionnent")]
    public function receptionReserveeAuxIntermediaires(): void
    {
        $this->demarrer();

        foreach (['Abidjan', 'Korhogo'] as $gare) {
            $this->action('receptionner', $gare);
            $this->assertStatut(400);
        }
    }

    #[Test]
    #[TestDox('Seul le terminus clôture le voyage')]
    public function clotureReserveeAuTerminus(): void
    {
        $this->demarrer();

        $this->action('cloturer', 'Bouaké');
        $this->assertStatut(400);

        $this->action('cloturer', 'Korhogo');
        $this->assertStatut(200);

        self::assertNotNull($this->relire(Voyage::class, $this->voyageId)->getDatearriveereelle());
    }

    #[Test]
    #[TestDox("Un voyage clôturé ne se réceptionne plus")]
    public function receptionApresCloture(): void
    {
        $this->demarrer();
        $this->action('cloturer', 'Korhogo');
        $this->assertStatut(200);

        $this->action('receptionner', 'Bouaké');
        $this->assertStatut(400);
    }

    #[Test]
    #[TestDox("La réception fait échoir les réservations dont la gare de montée est dépassée")]
    public function receptionLibereLesReservationsDepassees(): void
    {
        $maintenant = new DateTimeImmutable();
        $this->demarrer();
        $this->action('receptionner', 'Bouaké');
        $this->assertStatut(200);

        /*
            Ce n'est PAS le statut qui bascule — il reste EN_ATTENTE jusqu'au passage du cron
            d'expiration. Ce qui change, c'est l'ÉCHÉANCE, ramenée à l'instant du passage : une
            réservation échue ne tient plus de place ('findTenantPlacePourVoyage' l'écarte), la
            place est donc revendable immédiatement, sans attendre aucune tâche planifiée.
        */
        $amont = $this->relire(Reservation::class, $this->reservationAmontId);
        self::assertLessThanOrEqual(
            $maintenant->modify('+1 minute'),
            $amont->getDateexpiration(),
            'le car est passé à Abidjan : la place doit être rendue tout de suite'
        );

        // Bouaké est la position courante : le car va encore y prendre son passager.
        $aval = $this->relire(Reservation::class, $this->reservationAvalId);
        self::assertGreaterThan(
            $maintenant,
            $aval->getDateexpiration(),
            'les montées en aval gardent leur échéance'
        );
    }

    #[Test]
    #[TestDox("Le départ d'une gare intermédiaire s'enregistre séparément de la réception")]
    public function repartir(): void
    {
        $this->demarrer();
        $this->action('receptionner', 'Bouaké');
        $this->assertStatut(200);

        // Arrivée et départ sont deux horodatages distincts : leur écart est le temps à quai.
        $this->action('repartir', 'Bouaké');
        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("La clôture livre les bagages encore embarqués")]
    public function clotureLivreLesBagages(): void
    {
        $this->demarrer();
        $this->action('cloturer', 'Korhogo');
        $this->assertStatut(200);

        self::assertSame(
            'LIVRE',
            $this->relire(Bagage::class, $this->bagageId)->getStatut(),
            'le bagage arrive au terminus avec son voyage'
        );
    }

    #[Test]
    #[TestDox("Une gare étrangère à la ligne ne peut rien faire sur le voyage")]
    public function gareHorsLigne(): void
    {
        $ailleurs = $this->scenario->reseau(['Daloa', 'Man'], entreprise: $this->reseau->entreprise);
        $agentDaloa = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $ailleurs->gare('Daloa'),
            roles: ['ROLE_ADMIN_GARE']
        );

        $this->requete('PATCH', sprintf('/api/voyages/%d/receptionner', $this->voyageId), $agentDaloa, []);

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
    }

    /** Marque le départ réel : plusieurs gardes ne s'activent qu'une fois le car parti. */
    private function demarrer(): void
    {
        $this->action('demarrer', 'Abidjan');
        $this->assertStatut(200);
    }

    private function action(string $action, string $gare): void
    {
        $this->requete('PATCH', sprintf('/api/voyages/%d/%s', $this->voyageId, $action), $this->agents[$gare], []);
    }
}

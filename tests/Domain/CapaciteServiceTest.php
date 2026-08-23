<?php

namespace App\Tests\Domain;

use App\Domain\Enum\ReservationStatus;
use App\Domain\Enum\TicketStatus;
use App\Domain\Service\CapaciteService;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * La règle la plus contre-intuitive de l'application, et celle qui régresse le plus facilement :
 * la disponibilité se juge AU POINT DE MONTÉE, en comptant des SIÈGES et non des passagers.
 *
 * Chaque test nomme la conséquence métier qu'il protège. Plusieurs de ces comportements
 * ressemblent à des bugs quand on les découvre sans le contexte (un siège vendu deux fois, un
 * passager qui ne montera pas) : ce sont des choix d'exploitation assumés, et les « corriger »
 * casserait la priorité amont.
 */
final class CapaciteServiceTest extends IntegrationTestCase
{
    private CapaciteService $capacite;

    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capacite = $this->service(CapaciteService::class);
        // Abidjan(0) → Yamoussoukro(1) → Bouaké(2) → Korhogo(3)
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo']);
    }

    #[Test]
    #[TestDox('La capacité vient du car affecté, et retombe sur les places prévues sans car')]
    public function capaciteEffective(): void
    {
        $car = $this->scenario->car($this->reseau->entreprise, 40);

        $avecCar = $this->scenario->voyage($this->reseau, car: $car, placesprevues: 12);
        $sansCar = $this->scenario->voyage($this->reseau, placesprevues: 12);
        $sansRien = $this->scenario->voyage($this->reseau);

        self::assertSame(40, $this->capacite->capaciteEffective($avecCar), 'le car affecté prime sur la prévision');
        self::assertSame(12, $this->capacite->capaciteEffective($sansCar), 'sans car, la prévision fait foi');
        self::assertNull($this->capacite->capaciteEffective($sansRien), 'sans car ni prévision, la capacité est indéterminée');
    }

    #[Test]
    #[TestDox("Une vente en aval ne consomme aucune place à l'amont : la gare amont est prioritaire")]
    public function venteAvalNeBloquePasAmont(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 2));

        // Les deux sièges du car sont vendus, mais seulement à partir de Bouaké.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');
        $this->scenario->billet($this->reseau, $voyage, siege: 2, de: 'Bouaké', a: 'Korhogo');

        // Depuis Abidjan, le car est encore vide : la vente doit rester possible.
        self::assertSame(
            2,
            $this->placesDepuis($voyage, 'Abidjan', 'Korhogo'),
            "une vente depuis l'aval ne doit jamais réduire la disponibilité de l'amont"
        );

        // Et à Bouaké, où les passagers sont effectivement à bord, il n'y a plus rien.
        self::assertSame(0, $this->placesDepuis($voyage, 'Bouaké', 'Korhogo'));
    }

    #[Test]
    #[TestDox('On compte des sièges, pas des passagers : deux billets sur un même siège immobilisent un siège')]
    public function occupationCompteDesSiegesPasDesPassagers(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 2));

        // Surbooking amont assumé : le siège 1 porte deux passagers sur Bouaké → Korhogo.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');

        // Deux passagers, mais un seul siège pris : le siège 2 reste vendable depuis Bouaké.
        self::assertSame(
            1,
            $this->placesDepuis($voyage, 'Bouaké', 'Korhogo'),
            'compter les passagers saturerait le décompte et rendrait la revente impossible'
        );
    }

    #[Test]
    #[TestDox('Un passager descendu en route libère son siège pour les gares suivantes')]
    public function descenteAnticipeeLibereLeSiege(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        // Vendu jusqu'à Korhogo, mais descendu à Bouaké : le siège est réellement vide au-delà.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo', descendu: 'Bouaké');

        self::assertSame(0, $this->placesDepuis($voyage, 'Yamoussoukro', 'Bouaké'), 'avant la descente, le siège est pris');
        self::assertSame(
            1,
            $this->placesDepuis($voyage, 'Bouaké', 'Korhogo'),
            'la gare de descente doit pouvoir revendre le siège libéré'
        );
    }

    #[Test]
    #[TestDox('Un billet annulé ou reporté ne tient plus de place')]
    public function billetDesisteLibereLaPlace(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 2));

        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo', statut: TicketStatus::STATUT_ANNULE);
        $this->scenario->billet($this->reseau, $voyage, siege: 2, de: 'Abidjan', a: 'Korhogo', statut: TicketStatus::STATUT_REPORTE);

        self::assertSame(2, $this->placesDepuis($voyage, 'Abidjan', 'Korhogo'));
    }

    #[Test]
    #[TestDox("Une réservation vivante tient une place, qu'elle soit payée ou en attente de paiement")]
    public function reservationTientUnePlace(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 3));

        $this->scenario->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo', ReservationStatus::STATUT_EN_ATTENTE);
        $this->scenario->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo', ReservationStatus::STATUT_CONFIRMEE);

        self::assertSame(
            1,
            $this->placesDepuis($voyage, 'Abidjan', 'Korhogo'),
            "on ne vend pas par-dessus une réservation : sinon on encaisse un client sans pouvoir lui émettre de billet"
        );
    }

    #[Test]
    #[TestDox('Une réservation expirée, annulée ou à régulariser ne tient plus de place')]
    public function reservationEteinteNeTientPlusDePlace(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        foreach ([
            ReservationStatus::STATUT_EXPIREE,
            ReservationStatus::STATUT_ANNULEE,
            ReservationStatus::STATUT_A_REGULARISER,
        ] as $statut) {
            $this->scenario->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo', $statut);
        }

        self::assertSame(
            1,
            $this->placesDepuis($voyage, 'Abidjan', 'Korhogo'),
            'une place bloquée par une réservation éteinte ferait refuser des ventes sans raison visible'
        );
    }

    #[Test]
    #[TestDox('Une réservation ne se bloque pas elle-même au moment de son paiement')]
    public function reservationExclueDeSonPropreCalcul(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        // Seule place du car, tenue par la réservation qu'on s'apprête à encaisser.
        $reservation = $this->scenario->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo');

        self::assertTrue(
            $this->capacite->placeEncoreDisponiblePour($reservation),
            'compter la réservation dans son propre calcul ferait échouer tout encaissement'
        );
    }

    #[Test]
    #[TestDox("Le paiement est refusé si la capacité a fondu entre-temps (car plus petit, ventes au guichet)")]
    public function paiementRefuseSiCapaciteDevenueInsuffisante(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        $reservation = $this->scenario->reservation($this->reseau, $voyage, 'Abidjan', 'Korhogo');
        // L'unique siège a été vendu au guichet depuis.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');

        self::assertFalse($this->capacite->placeEncoreDisponiblePour($reservation));
    }

    #[Test]
    #[TestDox('La vente est refusée quand le tronçon est plein, et quand aucune capacité n\'est définie')]
    public function assertPlaceDisponible(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Plus de place disponible');
        $this->capacite->assertPlaceDisponible(
            $voyage,
            $this->reseau->ordre('Abidjan'),
            $this->reseau->ordre('Korhogo'),
            $this->reseau->identreprise()
        );
    }

    #[Test]
    #[TestDox("Sans car ni places prévues, la vente est refusée plutôt que d'être traitée comme illimitée")]
    public function assertPlaceDisponibleSansCapacite(): void
    {
        $voyage = $this->scenario->voyage($this->reseau);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Aucune capacité définie');
        $this->capacite->assertPlaceDisponible(
            $voyage,
            $this->reseau->ordre('Abidjan'),
            $this->reseau->ordre('Korhogo'),
            $this->reseau->identreprise()
        );
    }

    #[Test]
    #[TestDox("Le passager dont le siège est déjà pris à son point de montée est évincé")]
    public function billetEvince(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        $amont = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');
        // Même siège, monte plus tard, alors que l'amont est encore à bord.
        $aval = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');

        $evinces = $this->capacite->billetsEvinces($voyage, $this->reseau->identreprise());

        self::assertArrayHasKey($aval->getId(), $evinces, 'le passager monté plus tard perd le siège');
        self::assertArrayNotHasKey($amont->getId(), $evinces, "l'amont garde sa place");
    }

    #[Test]
    #[TestDox("Deux billets sur un même siège mais sur des tronçons disjoints : personne n'est évincé")]
    public function pasEvictionSurTronconsDisjoints(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        // Revente légitime : le premier descend là où le second monte.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Bouaké');
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');

        self::assertSame([], $this->capacite->billetsEvinces($voyage, $this->reseau->identreprise()));
    }

    #[Test]
    #[TestDox("Un billet déjà évincé n'évince personne : il ne prend aucune place")]
    public function billetEvinceNEvincePasATonTour(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));

        // Trois billets sur le MÊME siège, montées successives.
        $abidjan = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Bouaké');
        $yamoussoukro = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Yamoussoukro', a: 'Korhogo');
        $bouake = $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');

        $evinces = $this->capacite->billetsEvinces($voyage, $this->reseau->identreprise());

        self::assertArrayNotHasKey($abidjan->getId(), $evinces, "le plus amont garde le siège");
        self::assertArrayHasKey($yamoussoukro->getId(), $evinces, 'son siège est occupé quand il monte');
        // Le point clé : Yamoussoukro étant évincé, il ne monte pas — le siège se libère bien à
        // Bouaké. Comparer les intervalles deux à deux évincerait Bouaké à tort.
        self::assertArrayNotHasKey(
            $bouake->getId(),
            $evinces,
            "un passager qui ne monte pas ne peut pas priver un troisième de sa place"
        );
    }

    #[Test]
    #[TestDox("L'occupation maximale compte les sièges immobilisés, pas les passagers transportés")]
    public function occupationMaximale(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 2));

        // Deux passagers se partagent le siège 1 sur Bouaké → Korhogo, le siège 2 est pris tout du long.
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');
        $this->scenario->billet($this->reseau, $voyage, siege: 2, de: 'Abidjan', a: 'Korhogo');

        // Trois passagers, mais deux sièges : exiger un car de 3 places refuserait la simple
        // modification du voyage à véhicule inchangé.
        self::assertSame(2, $this->capacite->occupationMaximale($voyage, $this->reseau->identreprise()));
    }

    #[Test]
    #[TestDox("Le périmètre entreprise est respecté : les billets d'une autre compagnie ne sont pas comptés")]
    public function decompteBornéALEntreprise(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, car: $this->scenario->car($this->reseau->entreprise, 1));
        $this->scenario->billet($this->reseau, $voyage, siege: 1, de: 'Abidjan', a: 'Korhogo');

        // Interrogé au nom d'une AUTRE compagnie, le service ne doit voir aucun billet.
        self::assertSame(
            1,
            $this->capacite->placesDisponibles(
                $voyage,
                $this->reseau->ordre('Abidjan'),
                $this->reseau->ordre('Korhogo'),
                identreprise: $this->reseau->identreprise() + 999
            )
        );
    }

    /** Raccourci de lecture : « combien de places reste-t-il pour qui monte à X et descend à Y ». */
    private function placesDepuis(object $voyage, string $de, string $a): int
    {
        return $this->capacite->placesDisponibles(
            $voyage,
            $this->reseau->ordre($de),
            $this->reseau->ordre($a),
            $this->reseau->identreprise()
        );
    }
}

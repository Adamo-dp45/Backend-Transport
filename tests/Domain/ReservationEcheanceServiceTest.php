<?php

namespace App\Tests\Domain;

use App\Domain\Enum\ReservationStatus;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Gare;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'heure de passage et les échéances qui s'y calent.
 *
 * Tout le module Réservation repose sur une seule idée : les délais se comptent depuis l'heure à
 * laquelle le car passe À LA GARE DU CLIENT, jamais depuis le départ du voyage. Cette heure n'est
 * pas stockée, elle est RECALCULÉE en sommant les tronçons — une régression ici décale
 * silencieusement toutes les échéances, les retards et la ponctualité, sans rien casser de visible.
 */
final class ReservationEcheanceServiceTest extends IntegrationTestCase
{
    private ReservationEcheanceService $echeances;

    protected function setUp(): void
    {
        parent::setUp();

        $this->echeances = $this->service(ReservationEcheanceService::class);
    }

    #[Test]
    #[TestDox("L'heure de passage est le départ plus la somme des tronçons menant à la gare")]
    public function heurePassageSommeLesTroncons(): void
    {
        // Abidjan → Yamoussoukro (+3 h) → Bouaké (+2 h) → Korhogo (+4 h)
        $reseau = $this->reseauComplet();
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        self::assertSame('07:00', $this->passage($voyage, $reseau->gare('Abidjan')), "l'origine part à l'heure annoncée");
        self::assertSame('10:00', $this->passage($voyage, $reseau->gare('Yamoussoukro')));
        self::assertSame('12:00', $this->passage($voyage, $reseau->gare('Bouaké')));
        self::assertSame('16:00', $this->passage($voyage, $reseau->gare('Korhogo')), 'cumul des trois tronçons');
    }

    #[Test]
    #[TestDox("Sur un départ partiel, le décalage est relatif à la gare de départ réelle, pas à l'origine de la ligne")]
    public function heurePassageSurDepartPartiel(): void
    {
        $reseau = $this->reseauComplet();

        // Bouaké lance son propre départ à 08:00 : c'est l'heure de BOUAKÉ, pas celle d'Abidjan.
        $voyage = $this->scenario->voyage(
            $reseau,
            depart: new DateTimeImmutable('2026-03-10 08:00'),
            provenance: 'Bouaké'
        );

        self::assertSame('08:00', $this->passage($voyage, $reseau->gare('Bouaké')), 'la provenance part à l\'heure annoncée');
        self::assertSame(
            '12:00',
            $this->passage($voyage, $reseau->gare('Korhogo')),
            'seul le tronçon Bouaké → Korhogo (4 h) compte : compter depuis Abidjan donnerait 16:00'
        );
    }

    #[Test]
    #[TestDox("Une gare située en amont de la provenance retombe sur l'heure de départ, sans heure antidatée")]
    public function gareEnAmontDeLaProvenance(): void
    {
        $reseau = $this->reseauComplet();
        $voyage = $this->scenario->voyage(
            $reseau,
            depart: new DateTimeImmutable('2026-03-10 08:00'),
            provenance: 'Bouaké'
        );

        // Le car ne passe pas par Abidjan : renvoyer « 08:00 − 5 h » n'aurait aucun sens.
        self::assertSame('08:00', $this->passage($voyage, $reseau->gare('Abidjan')));
    }

    #[Test]
    #[TestDox("Si un seul tronçon n'est pas renseigné, tout retombe sur l'heure de départ (règle du tout-ou-rien)")]
    public function tronconManquantFaitRetomberSurLeDepart(): void
    {
        // Le tronçon menant à Bouaké est absent : la ligne n'est pas exploitable pour les horaires.
        $reseau = $this->scenario->reseau(
            ['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo'],
            [null, 180, null, 240]
        );
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        self::assertSame('10:00', $this->passage($voyage, $reseau->gare('Yamoussoukro')), 'avant le trou, le cumul tient');
        self::assertSame(
            '07:00',
            $this->passage($voyage, $reseau->gare('Korhogo')),
            'au-delà du trou, mieux vaut une échéance stricte qu\'une échéance inventée'
        );
    }

    #[Test]
    #[TestDox("Une gare hors de la ligne retombe sur l'heure de départ")]
    public function gareHorsLigne(): void
    {
        $reseau = $this->reseauComplet();
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        $etrangere = new Gare();
        $etrangere->setLibelle('Gare inconnue')->setChefgare('X')->setContact1('+225 00')->setStatut('ACTIF');

        self::assertSame('07:00', $this->passage($voyage, $etrangere));
    }

    #[Test]
    #[TestDox('La limite de présentation est le passage moins le délai de la compagnie')]
    public function limitePresentation(): void
    {
        $reseau = $this->reseauComplet();
        $this->scenario->parametreReservation($reseau->entreprise, presentationMinutes: 20);
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        // Le car passe à Bouaké à 12:00 → guichet fermé à 11:40 pour qui monte là.
        $limite = $this->echeances->limitePresentationPour($voyage, $reseau->gare('Bouaké'), $reseau->identreprise());

        self::assertSame('2026-03-10 11:40', $limite?->format('Y-m-d H:i'));
    }

    #[Test]
    #[TestDox("Le délai de présentation dépend de la gare de montée, pas du départ du voyage")]
    public function limitePresentationVarieSelonLaGare(): void
    {
        $reseau = $this->reseauComplet();
        $this->scenario->parametreReservation($reseau->entreprise, presentationMinutes: 15);
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        $abidjan = $this->echeances->limitePresentationPour($voyage, $reseau->gare('Abidjan'), $reseau->identreprise());
        $korhogo = $this->echeances->limitePresentationPour($voyage, $reseau->gare('Korhogo'), $reseau->identreprise());

        self::assertSame('06:45', $abidjan?->format('H:i'));
        self::assertSame(
            '15:45',
            $korhogo?->format('H:i'),
            "qui monte au terminus n'a rien à faire au guichet quand le car quitte l'origine"
        );
    }

    #[Test]
    #[TestDox('La limite de paiement est le délai de paiement quand il tombe avant la présentation')]
    public function limitePaiementBorneeParLeDelaiDePaiement(): void
    {
        $reseau = $this->reseauComplet();
        $this->scenario->parametreReservation($reseau->entreprise, presentationMinutes: 15, paiementMinutes: 30);

        // Réservation créée bien avant le départ : c'est le délai de paiement qui contraint.
        $limite = $this->echeances->limitePaiement(
            new DateTimeImmutable('2026-03-10 06:00'),
            new DateTimeImmutable('2026-03-10 12:00'),
            $reseau->identreprise()
        );

        self::assertSame('06:30', $limite->format('H:i'));
    }

    #[Test]
    #[TestDox('La limite de paiement ne dépasse jamais la limite de présentation')]
    public function limitePaiementBorneeParLaPresentation(): void
    {
        $reseau = $this->reseauComplet();
        $this->scenario->parametreReservation($reseau->entreprise, presentationMinutes: 15, paiementMinutes: 30);

        // Réservation prise 10 minutes avant le passage : payer 30 min plus tard serait absurde,
        // le car serait parti.
        $limite = $this->echeances->limitePaiement(
            new DateTimeImmutable('2026-03-10 11:50'),
            new DateTimeImmutable('2026-03-10 12:00'),
            $reseau->identreprise()
        );

        self::assertSame('11:45', $limite->format('H:i'), 'la présentation prime dès qu\'elle tombe plus tôt');
    }

    #[Test]
    #[TestDox("L'échéance applicable bascule du paiement à la présentation une fois la réservation payée")]
    public function echeanceApplicableSelonLeStatut(): void
    {
        $reseau = $this->reseauComplet();
        $this->scenario->parametreReservation($reseau->entreprise, presentationMinutes: 15, paiementMinutes: 30);
        $voyage = $this->scenario->voyage($reseau, depart: new DateTimeImmutable('2026-03-10 07:00'));

        $impayee = $this->scenario->reservation(
            $reseau,
            $voyage,
            'Bouaké',
            'Korhogo',
            ReservationStatus::STATUT_EN_ATTENTE,
            creee: new DateTimeImmutable('2026-03-10 06:00')
        );

        $payee = $this->scenario->reservation($reseau, $voyage, 'Bouaké', 'Korhogo', ReservationStatus::STATUT_CONFIRMEE);

        // Impayée : 06:00 + 30 min de délai de paiement.
        self::assertSame('06:30', $this->echeances->echeanceApplicable($impayee, $voyage)->format('H:i'));
        // Payée : la place est garantie jusqu'à 15 min avant le passage à Bouaké (12:00).
        self::assertSame('11:45', $this->echeances->echeanceApplicable($payee, $voyage)->format('H:i'));
    }

    /** Abidjan(0) → Yamoussoukro(+3 h) → Bouaké(+2 h) → Korhogo(+4 h). */
    private function reseauComplet(): Reseau
    {
        return $this->scenario->reseau(
            ['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo'],
            [null, 180, 120, 240]
        );
    }

    private function passage(object $voyage, Gare $gare): ?string
    {
        return $this->echeances->heurePassage($voyage, $gare)?->format('H:i');
    }
}

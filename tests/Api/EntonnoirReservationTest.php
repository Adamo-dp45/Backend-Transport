<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'ENTONNOIR DES RÉSERVATIONS, CANAL PAR CANAL : émises → payées → présentées.
 *
 * Le module savait déjà quelle part des bons vient du mobile et quelle part est payée, mais jamais
 * LE CROISEMENT : impossible de dire si le mobile convertit mieux ou moins bien que le guichet. Or
 * c'est la seule question qui se décide — un canal qui apporte 60 % du volume et n'en honore qu'un
 * tiers ne vaut pas celui qui apporte 40 % et les honore tous.
 *
 * DEUX RÈGLES QUE CE TEST TIENT, et qu'un œil sur les chiffres ne corrigerait pas :
 *
 *  1. UN NO-SHOW A PAYÉ. 'A_REGULARISER' compte dans les PAYÉES — c'est le voyage qu'il n'a pas
 *     pris, pas l'argent. L'exclure sous-estimerait la conversion du canal et sa recette ; le ranger
 *     avec les PERDUES ferait croire à un manque à gagner qui n'existe pas, puisque le bon reste
 *     régularisable avec pénalité et complément.
 *  2. LE TAUX DE PRÉSENTATION SE RAPPORTE AUX PAYÉES, pas aux émises. Un bon jamais payé n'avait
 *     aucun client à présenter : le compter là ferait porter au canal une déperdition déjà comptée
 *     dans sa conversion, et les deux taux diraient deux fois la même chose.
 */
final class EntonnoirReservationTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private Voyage $voyage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 30)
        );
    }

    #[Test]
    #[TestDox('Chaque canal a son propre entonnoir : le volume ne dit pas la conversion')]
    public function chaqueCanalASonEntonnoir(): void
    {
        // MOBILE : beaucoup de volume, peu d'argent — 4 bons, 1 payé.
        $this->bon('MOBILE', ReservationStatus::STATUT_CONFIRMEE, payee: true);
        $this->bon('MOBILE', ReservationStatus::STATUT_EN_ATTENTE);
        $this->bon('MOBILE', ReservationStatus::STATUT_EXPIREE);
        $this->bon('MOBILE', ReservationStatus::STATUT_ANNULEE);

        // GUICHET : peu de volume, tout payé.
        $this->bon('GUICHET', ReservationStatus::STATUT_CONFIRMEE, payee: true);
        $this->bon('GUICHET', ReservationStatus::STATUT_CONFIRMEE, payee: true);

        $canaux = $this->entonnoir();

        self::assertSame(4, $canaux['MOBILE']['total']);
        self::assertSame(1, $canaux['MOBILE']['payees']);
        self::assertSame(2, $canaux['MOBILE']['perdues'], 'expirée + annulée');
        self::assertSame(25.0, (float) $canaux['MOBILE']['tauxConversion']);

        self::assertSame(2, $canaux['GUICHET']['total']);
        self::assertSame(
            100.0,
            (float) $canaux['GUICHET']['tauxConversion'],
            "le MOBILE apporte deux fois plus de volume et quatre fois moins de conversion. C'est "
            . "exactement ce que la part de marché seule ne dit pas, et qui se décide"
        );
    }

    #[Test]
    #[TestDox("Un no-show compte dans les PAYÉES — il a payé, c'est le voyage qu'il n'a pas pris")]
    public function leNoShowCompteDansLesPayees(): void
    {
        $this->bon('MOBILE', ReservationStatus::STATUT_A_REGULARISER, payee: true);

        $mobile = $this->entonnoir()['MOBILE'];

        self::assertSame(
            1,
            $mobile['payees'],
            "'A_REGULARISER' est un bon PAYÉ : l'exclure sous-estimerait la conversion du canal"
        );
        self::assertSame(1, $mobile['aRegulariser']);
        self::assertSame(
            0,
            $mobile['perdues'],
            "et ce n'est PAS une perte : le bon reste régularisable, avec pénalité et complément. "
            . "Le ranger avec les perdues ferait croire à un manque à gagner qui n'existe pas"
        );
        self::assertSame(0, $mobile['presentees'], "aucun billet émis : c'est bien là que se loge le no-show");
    }

    #[Test]
    #[TestDox('Le taux de présentation se rapporte aux PAYÉES, jamais aux émises')]
    public function laPresentationSeRapporteAuxPayees(): void
    {
        // Un bon payé dont le client s'est présenté (billet émis).
        $presente = $this->bon('MOBILE', ReservationStatus::STATUT_CONFIRMEE, payee: true);
        $billet = $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo', prix: 9000);
        $presente->setTicket($billet);

        // Un bon payé dont le client ne s'est pas présenté.
        $this->bon('MOBILE', ReservationStatus::STATUT_A_REGULARISER, payee: true);

        // Trois bons jamais payés : ils n'avaient aucun client à présenter.
        $this->bon('MOBILE', ReservationStatus::STATUT_EN_ATTENTE);
        $this->bon('MOBILE', ReservationStatus::STATUT_EXPIREE);
        $this->bon('MOBILE', ReservationStatus::STATUT_ANNULEE);
        $this->em->flush();

        $mobile = $this->entonnoir()['MOBILE'];

        self::assertSame(5, $mobile['total']);
        self::assertSame(2, $mobile['payees']);
        self::assertSame(1, $mobile['presentees']);
        self::assertSame(
            50.0,
            (float) $mobile['tauxPresentation'],
            "1 présenté sur 2 PAYÉS. Rapporté aux 5 émises on lirait 20 %, et ce taux redirait la "
            . "déperdition déjà mesurée par la conversion — deux chiffres pour une seule information"
        );
    }

    #[Test]
    #[TestDox("Le taux global est la moyenne pondérée des canaux, pas la valeur du dernier")]
    public function leTauxGlobalResteCoherentAvecLesCanaux(): void
    {
        /*
            LA SENTINELLE D'UN DÉFAUT RÉEL, trouvé en croisant les surfaces le 08/10/2026 : la boucle
            de l'entonnoir réutilisait '$payees', LA VARIABLE DU CALCUL GLOBAL déjà fixée au-dessus.
            À la sortie de boucle elle valait la dernière source, et le taux global rendait 14,3 % au
            lieu de 42,9 % — sous le libellé « payées / total » d'une carte affichant 2 payées sur 7.

            CE QUE CE TEST TIENT, et qu'aucun des trois précédents ne voyait : ils ne vérifiaient que
            'parSource'. Le global était faux à côté d'eux, au vert. Le montage ci-dessous est celui
            qui révèle l'écrasement : le GUICHET passe EN DERNIER avec un seul bon payé, donc un
            global contaminé afficherait 1/7 — exactement ce qui était servi en production.
        */
        $this->bon('MOBILE', ReservationStatus::STATUT_CONFIRMEE, payee: true);
        $this->bon('MOBILE', ReservationStatus::STATUT_A_REGULARISER, payee: true);
        $this->bon('MOBILE', ReservationStatus::STATUT_EN_ATTENTE);
        $this->bon('MOBILE', ReservationStatus::STATUT_EXPIREE);
        $this->bon('MOBILE', ReservationStatus::STATUT_ANNULEE);
        $this->bon('MOBILE', ReservationStatus::STATUT_ANNULEE);
        $this->bon('GUICHET', ReservationStatus::STATUT_CONFIRMEE, payee: true);

        $this->requete('GET', '/api/stats/reservations?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);
        $global = $this->reponseJson();

        self::assertSame(
            3,
            $global['confirmees'] + $global['aRegulariser'],
            'trois bons payés : deux confirmés, un à régulariser'
        );
        self::assertSame(
            42.9,
            (float) $global['tauxConversion'],
            "3 payées sur 7 émises. Le GUICHET est passé en dernier avec 1 payée : un global écrasé "
            . "par la boucle afficherait 14,3 %, et c'est ce qui était servi"
        );

        $payeesDesCanaux = 0;
        $emisesDesCanaux = 0;
        foreach ($global['parSource'] as $canal) {
            $payeesDesCanaux += $canal['payees'];
            $emisesDesCanaux += $canal['total'];
        }

        self::assertSame($global['total'], $emisesDesCanaux, 'aucun bon ne doit manquer à la répartition par canal');
        self::assertSame(
            round($payeesDesCanaux * 100 / $emisesDesCanaux, 1),
            (float) $global['tauxConversion'],
            "LE GLOBAL EST LA MOYENNE PONDÉRÉE DES CANAUX, par construction. C'est cette égalité qui "
            . "a fait tomber le défaut : des canaux à 33 % et 100 % tous DEUX au-dessus d'un global "
            . "censé être leur moyenne, ce qui est impossible et ne se voyait qu'en les mettant côte "
            . "à côte"
        );
    }

    /** @return array<string, array<string, mixed>> */
    private function entonnoir(): array
    {
        $this->requete('GET', '/api/stats/reservations?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);

        $parSource = [];
        foreach ($this->reponseJson()['parSource'] as $s) {
            $parSource[$s['source']] = $s;
        }

        return $parSource;
    }

    private function bon(string $source, ReservationStatus $statut, bool $payee = false): Reservation
    {
        $bon = $this->scenario->reservation(
            $this->reseau, $this->voyage, 'Abidjan', 'Korhogo',
            statut: $statut,
            prix: 9000,
            etatpaiement: $payee ? 'PAYE' : 'EN_ATTENTE_PAIEMENT',
            datepaiement: $payee ? new DateTimeImmutable('-1 hour') : null
        );
        $bon->setSource($source);
        $this->em->flush();

        return $bon;
    }
}

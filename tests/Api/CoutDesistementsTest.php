<?php

namespace App\Tests\Api;

use App\Domain\Enum\TicketStatus;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * CE QUE LES DÉSISTEMENTS COÛTENT, EN ARGENT.
 *
 * La section « désistements » ne comptait que des BILLETS : on savait combien disparaissaient,
 * jamais combien d'argent ressortait. Entre « 42 annulations ce mois-ci » et « 380 000 F remboursés,
 * dont 120 000 de notre fait », seule la seconde phrase se décide.
 *
 * DEUX PIÈGES, et le premier est celui qui se reproduit le plus souvent dans ce projet :
 *
 *  1. LE REMBOURSEMENT COURT SUR TROIS ENTITÉS — le billet désisté, ses bagages annulés en cascade,
 *     et le courrier repris avant départ. C'est le même triplet que la caisse
 *     ('CaisseTheoriqueService'), et n'en compter qu'une partie annonce un coût systématiquement
 *     SOUS-ÉVALUÉ. Un chiffre trop bas n'alarme personne : c'est ce qui le rend durable.
 *  2. LA CORBEILLE EXCLUT DE TOUT TOTAL D'ARGENT. Une pièce mise à la corbeille n'aurait jamais dû
 *     être saisie — son remboursement non plus.
 */
final class CoutDesistementsTest extends ApiTestCase
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
    #[TestDox('Le coût additionne les billets, leurs bagages et les courriers')]
    public function leCoutCourtSurLesTroisEntites(): void
    {
        $billet = $this->billetRembourse(12000, siege: 1);
        $bagage = $this->scenario->bagage($this->reseau, $billet, statut: 'ANNULE', montant: 2500);
        $bagage->setMontantrembourse(2500);

        $courrier = $this->scenario->courrier($this->reseau, 'Abidjan', 'Korhogo', montant: 4000, statut: 'ANNULE');
        $courrier->setMontantrembourse(4000);
        $this->em->flush();

        $rb = $this->remboursements();

        self::assertSame(12000, $rb['billets']);
        self::assertSame(2500, $rb['bagages'], 'les bagages annulés en cascade sont remboursés eux aussi');
        self::assertSame(4000, $rb['courriers']);
        self::assertSame(
            18500,
            $rb['total'],
            "le remboursement court sur TROIS entités — même triplet que la caisse. N'en compter "
            . "qu'une partie annonce un coût sous-évalué, et un chiffre trop bas n'alarme personne"
        );
        self::assertSame(3, $rb['nb']);
    }

    #[Test]
    #[TestDox("La part imputable à la compagnie est isolée : c'est elle qui se pilote")]
    public function laPartImputableEstIsolee(): void
    {
        // Un client qui renonce : subi.
        $this->billetRembourse(10000, siege: 1);

        // Un départ avancé après le paiement : la compagnie a provoqué le report.
        $duFaitDeLaCompagnie = $this->billetRembourse(15000, siege: 2, statut: TicketStatus::STATUT_REPORTE);
        $duFaitDeLaCompagnie->setDesistementImputableCompagnie(true);
        $this->em->flush();

        $rb = $this->remboursements();

        self::assertSame(25000, $rb['total'], 'les deux coûtent');
        self::assertSame(
            15000,
            $rb['imputableCompagnie'],
            "un client qui renonce ne se décide pas ; un horaire qu'on déplace, si. Sans cette "
            . "séparation, le coût pilotable reste noyé dans le coût subi"
        );
        self::assertSame(1, $rb['nbImputableCompagnie']);
    }

    #[Test]
    #[TestDox("Une pièce à la corbeille ne compte dans aucun montant")]
    public function laCorbeilleNEntrePas(): void
    {
        $this->billetRembourse(10000, siege: 1);

        $corbeille = $this->billetRembourse(90000, siege: 2);
        $corbeille->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();

        self::assertSame(
            10000,
            $this->remboursements()['total'],
            "la corbeille dit que la ligne n'aurait jamais dû être saisie : son remboursement non "
            . "plus. C'est la règle de tout total d'argent du projet"
        );
    }

    private function billetRembourse(int $prix, int $siege, TicketStatus $statut = TicketStatus::STATUT_ANNULE): object
    {
        $billet = $this->scenario->billet(
            $this->reseau, $this->voyage, $siege, 'Abidjan', 'Korhogo',
            prix: $prix, statut: $statut
        );
        $billet
            ->setMontantrembourse($prix)
            ->setDatedesistement(new DateTimeImmutable('-1 hour'));
        $this->em->flush();

        return $billet;
    }

    /** @return array<string, int> */
    private function remboursements(): array
    {
        $this->requete('GET', '/api/stats/billetterie/details?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson()['desistements']['rembourse'];
    }
}

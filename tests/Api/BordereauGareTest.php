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
 * Le BORDEREAU DE GARE (`GET /api/voyages/{id}/bordereau?gare=`) — le bilan de caisse d'un chef de
 * gare, document signé et archivé.
 *
 * Ici, la VENTILATION PAR DESTINATION : « Abidjan → Bouaké : 4 ». C'est le chiffre qu'on annonce au
 * chauffeur et qu'on recompte à l'embarquement, et celui qui explique une recette — dix courts
 * trajets et dix bouts en bout ne remplissent pas la caisse pareil.
 */
final class BordereauGareTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        // Abidjan (1) → Bouaké (2) → Korhogo (3)
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 20),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Voyage' => ['VOIR']]
        );
    }

    #[Test]
    #[TestDox('Les billets de la gare sont ventilés par destination, dans l\'ordre des arrêts')]
    public function ventilationParDestination(): void
    {
        // Vendus dans le désordre : la sortie doit suivre la ligne, pas l'ordre de saisie.
        $this->vendre(siege: 1, a: 'Korhogo');
        $this->vendre(siege: 2, a: 'Bouaké');
        $this->vendre(siege: 3, a: 'Korhogo');
        $this->vendre(siege: 4, a: 'Bouaké');
        $this->vendre(siege: 5, a: 'Korhogo');

        self::assertSame(
            [
                ['depart' => 'Abidjan', 'destination' => 'Bouaké', 'nbtickets' => 2],
                ['depart' => 'Abidjan', 'destination' => 'Korhogo', 'nbtickets' => 3],
            ],
            $this->ventilation($this->bordereau()),
            "l'ordre est celui où le car s'arrête, pas celui du nombre de billets"
        );
    }

    #[Test]
    #[TestDox('La ventilation ignore les billets désistés et ceux des autres gares')]
    public function ventilationIgnoreLeBruit(): void
    {
        $this->vendre(siege: 1, a: 'Korhogo');
        $this->vendre(siege: 2, a: 'Korhogo', statut: \App\Domain\Enum\TicketStatus::STATUT_ANNULE);
        // Vendu par Bouaké : il appartient au bordereau de Bouaké, pas à celui d'Abidjan.
        $this->scenario->billet($this->reseau, $this->voyage, siege: 3, de: 'Bouaké', a: 'Korhogo');

        $reponse = $this->bordereau();

        self::assertSame(
            [['depart' => 'Abidjan', 'destination' => 'Korhogo', 'nbtickets' => 1]],
            $this->ventilation($reponse)
        );
        self::assertSame(
            $reponse['nbtickets'],
            array_sum(array_column($reponse['destinations'], 'nbtickets')),
            'la ventilation doit toujours retomber sur le total affiché en tête du document'
        );
    }

    #[Test]
    #[TestDox('Une gare sans vente rend une ventilation vide, pas une erreur')]
    public function gareSansVente(): void
    {
        self::assertSame([], $this->bordereau()['destinations'] ?? null);
    }

    /**
     * La ventilation débarrassée des clés JSON-LD ('@type', '@id') que la sérialisation ajoute à
     * chaque DTO imbriqué : le test porte sur le contenu métier et son ORDRE, pas sur l'habillage.
     *
     * @param array<string, mixed> $bordereau
     *
     * @return list<array{depart:string, destination:string, nbtickets:int}>
     */
    private function ventilation(array $bordereau): array
    {
        return array_map(
            static fn (array $d): array => [
                'depart' => $d['depart'],
                'destination' => $d['destination'],
                'nbtickets' => $d['nbtickets'],
            ],
            $bordereau['destinations'] ?? []
        );
    }

    private function vendre(
        int $siege,
        string $a,
        \App\Domain\Enum\TicketStatus $statut = \App\Domain\Enum\TicketStatus::STATUT_VALIDE
    ): void {
        $this->scenario->billet($this->reseau, $this->voyage, siege: $siege, de: 'Abidjan', a: $a, statut: $statut);
    }

    /** @return array<string, mixed> */
    private function bordereau(): array
    {
        $this->requete('GET', sprintf(
            '/api/voyages/%d/bordereau?gare=%d',
            $this->voyage->getId(),
            $this->reseau->gare('Abidjan')->getId()
        ), $this->agent);

        $this->assertStatut(200);

        return $this->reponseJson();
    }
}

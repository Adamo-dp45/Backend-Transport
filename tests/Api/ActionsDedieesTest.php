<?php

namespace App\Tests\Api;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use App\Entity\Approvisionnement;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Depannage;
use App\Entity\Detailcourrier;
use App\Entity\Personnel;
use App\Entity\Piece;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Les actions SÉPARÉES de 'MODIFIER' : une permission par geste dont l'effet n'est pas une
 * modification de champ.
 *
 * Ce que ces tests protègent tient en une phrase : 'MODIFIER' ne doit plus ouvrir ces opérations.
 * Sous l'ancienne garde, un profil qui devait rectifier une saisie — un magasinier corrigeant un
 * libellé de pièce, un agent corrigeant un colis — héritait au passage de l'inventaire, de la caisse
 * ou de la déclaration de perte. La régression serait SILENCIEUSE : rien ne casse, tout s'ouvre.
 */
final class ActionsDedieesTest extends ApiTestCase
{
    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
    }

    // ----------------------------------------------------- LA GARDE DÉCLARÉE (contrat) ---------

    /** @return array<string, array{0: class-string, 1: string, 2: string}> */
    public static function gardesAttendues(): array
    {
        return [
            "ajustement d'inventaire" => [Piece::class, '/pieces/{id}/ajuster', "is_granted('AJUSTER', object)"],
            "annulation d'approvisionnement" => [Approvisionnement::class, '/approvisionnements/{id}/annuler', "is_granted('ANNULER', object)"],
            'annulation de dépannage' => [Depannage::class, '/depannages/{id}/annuler', "is_granted('ANNULER', object)"],
            'annulation de réservation' => [Reservation::class, '/reservations/{id}/annuler', "is_granted('ANNULER', object)"],
            'courrier déclaré perdu' => [Courrier::class, '/courriers/{id}/perdu', "is_granted('DECLARER_PERDU', object)"],
            'détail de courrier perdu' => [Detailcourrier::class, '/detailcourriers/{id}/perdu', "is_granted('DECLARER_PERDU', 'Courrier')"],
            'bagage déclaré perdu' => [Bagage::class, '/bagages/{id}/perdu', "is_granted('DECLARER_PERDU', object)"],
            "suspension d'un agent" => [Personnel::class, '/personnels/{id}/suspendre', "is_granted('ROLE_ADMIN')"],
        ];
    }

    /**
     * Test de CONTRAT sur l'expression déclarée.
     *
     * Pourquoi pas un 403 sur chaque cas : sur une opération ITEM, API Platform exécute le provider
     * AVANT d'évaluer 'security:'. Un identifiant inexistant rend donc 404 et masque complètement la
     * garde — un tel test passerait au vert même si l'opération était rouverte à 'MODIFIER'. Monter
     * un Approvisionnement ou un Dépannage complet pour chacun coûterait plus cher que ce qu'il
     * rapporte : l'expression déclarée EST ce que l'on a changé, et c'est elle qui régresserait.
     * Là où une vraie ligne est disponible, le COMPORTEMENT est vérifié plus bas.
     */
    #[Test]
    #[DataProvider('gardesAttendues')]
    #[TestDox('La garde déclarée ne repose plus sur MODIFIER : $_dataName')]
    public function gardeDeclaree(string $classe, string $route, string $attendue): void
    {
        $fabrique = static::getContainer()->get(ResourceMetadataCollectionFactoryInterface::class);

        foreach ($fabrique->create($classe) as $ressource) {
            foreach ($ressource->getOperations() as $operation) {
                // Les métadonnées portent le gabarit SANS le préfixe de route ('/api').
                if ($operation->getUriTemplate() === $route) {
                    self::assertSame($attendue, $operation->getSecurity(), $route);

                    return;
                }
            }
        }

        self::fail(sprintf('Opération %s introuvable sur %s.', $route, $classe));
    }

    // ------------------------------------------------- LE COMPORTEMENT, SUR DE VRAIES LIGNES ---

    #[Test]
    #[TestDox('Un bagage : MODIFIER ne déclare plus la perte, DECLARER_PERDU oui')]
    public function perteDUnBagage(): void
    {
        $bagage = $this->bagage();

        $correcteur = $this->agent(['Bagage' => ['VOIR', 'CREER', 'MODIFIER']]);
        $this->requete('PATCH', '/api/bagages/' . $bagage->getId() . '/perdu', $correcteur, []);
        $this->assertStatut(403);

        $habilite = $this->agent(['Bagage' => ['VOIR', 'DECLARER_PERDU']]);
        $this->requete('PATCH', '/api/bagages/' . $bagage->getId() . '/perdu', $habilite, []);
        self::assertNotSame(403, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox("Une réservation : MODIFIER ne l'annule plus, ANNULER oui")]
    public function annulationDUneReservation(): void
    {
        $reservation = $this->reservation();

        $correcteur = $this->agent(['Reservation' => ['VOIR', 'CREER', 'MODIFIER']]);
        $this->requete('PATCH', '/api/reservations/' . $reservation->getId() . '/annuler', $correcteur, []);
        $this->assertStatut(403);

        $habilite = $this->agent(['Reservation' => ['VOIR', 'ANNULER']]);
        $this->requete('PATCH', '/api/reservations/' . $reservation->getId() . '/annuler', $habilite, []);
        self::assertNotSame(403, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox("Savoir vendre un billet n'ouvre plus la gestion des réservations")]
    public function vendreUnBilletNOuvrePlusLesReservations(): void
    {
        // Le repli 'or is_granted(CREER, Ticket)' court-circuitait toute la permission 'Reservation' :
        // un profil sans AUCUN droit dessus encaissait, émettait et régularisait.
        $reservation = $this->reservation();
        $vendeur = $this->agent(['Ticket' => ['VOIR', 'CREER', 'MODIFIER']]);

        foreach (['confirmer', 'emettre-billet', 'regulariser', 'annuler'] as $geste) {
            $this->requete('PATCH', '/api/reservations/' . $reservation->getId() . '/' . $geste, $vendeur, []);
            self::assertSame(
                403,
                $this->client->getResponse()->getStatusCode(),
                $geste . " ne doit plus s'ouvrir sur la seule permission de vente"
            );
        }
    }

    // --------------------------------------------------------------------------------------------

    /** @param array<string, list<string>> $permissions */
    private function agent(array $permissions): User
    {
        return $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            $permissions
        );
    }

    private function reservation(): Reservation
    {
        return $this->scenario->reservation($this->reseau, $this->voyage(), 'Abidjan', 'Korhogo');
    }

    private function bagage(): Bagage
    {
        $voyage = $this->voyage();
        $billet = $this->scenario->billet($this->reseau, $voyage, 1, 'Abidjan', 'Korhogo');

        return $this->scenario->bagage($this->reseau, $billet);
    }

    private function voyage(): Voyage
    {
        return $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+4 hours')
        );
    }
}

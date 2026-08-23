<?php

namespace App\Tests\Api;

use App\Domain\Enum\TicketStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Activite;
use App\Entity\Ticket;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le DÉSISTEMENT d'un billet (`PATCH /api/tickets/{id}/desister`), dans ses deux formes.
 *
 *   ANNULATION : remboursement, motif obligatoire, siège libéré.
 *   REPORT     : un NOUVEAU billet est émis sur un autre départ, chaîné au premier.
 *
 * Le report subit les DEUX gardes de la vente sur le voyage cible — siège libre ET place
 * disponible. La seconde est la moins évidente et la plus importante : sans elle, un report passe
 * par-dessus une réservation (qui ne tient pas de siège, seulement une place), et le jour venu
 * aucun billet ne peut lui être émis. Le siège aurait été vendu deux fois.
 */
final class DesistementTest extends ApiTestCase
{
    private Reseau $reseau;

    private int $billetId;

    private int $voyageCibleId;

    private int $siegeCibleId;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);

        $origine = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+4 hours')
        );
        $this->billetId = (int) $this->scenario
            ->billet($this->reseau, $origine, siege: 1, de: 'Abidjan', a: 'Korhogo', prix: 15000)->getId();

        $cible = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+1 day')
        );
        $this->voyageCibleId = (int) $cible->getId();
        $this->siegeCibleId = (int) $this->scenario->siege($cible, 3)->getId();

        $this->agent = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan'),
            roles: ['ROLE_ADMIN_GARE']
        );
    }

    #[Test]
    #[TestDox("Une annulation passe le billet à ANNULE et libère son siège")]
    public function annulation(): void
    {
        $this->desister(['mode' => 'ANNULATION', 'motif' => 'Client absent au départ']);

        $this->assertStatut(200);
        $billet = $this->relire(Ticket::class, $this->billetId);
        self::assertSame(TicketStatus::STATUT_ANNULE->value, $billet->getStatut());
        self::assertNotNull($billet->getDatedesistement(), 'l\'horodatage trace le geste');

        /*
            Le JOURNAL suit, dans un vrai flux HTTP. Depuis que la trace est écrite APRÈS le flush
            métier (ActiviteFlushListener), la perdre serait silencieux : le désistement passerait,
            l'annulation ne serait imputable à personne, et rien ne le signalerait.
        */
        $trace = $this->em->getRepository(Activite::class)
            ->findOneBy(['type' => ActiviteLogger::TICKET_ANNULE, 'cibleid' => $this->billetId]);
        self::assertNotNull($trace, 'l\'annulation doit rester imputable à son auteur');
        self::assertNotNull($trace->getAuteur(), 'une trace sans auteur ne répond pas à « qui a fait quoi »');
    }

    #[Test]
    #[TestDox("Le motif est obligatoire à l'annulation : c'est la pièce d'audit")]
    public function annulationSansMotif(): void
    {
        $this->desister(['mode' => 'ANNULATION']);

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            TicketStatus::STATUT_VALIDE->value,
            $this->relire(Ticket::class, $this->billetId)->getStatut(),
            'un refus ne doit rien avoir modifié'
        );
    }

    #[Test]
    #[TestDox("Un report émet un NOUVEAU billet, chaîné au billet d'origine")]
    public function report(): void
    {
        $this->desister([
            'mode' => 'REPORT',
            'motif' => 'Report à la demande du client',
            'voyage' => '/api/voyages/' . $this->voyageCibleId,
            'siege' => '/api/sieges/' . $this->siegeCibleId,
        ]);

        $this->assertStatut(200);

        $origine = $this->relire(Ticket::class, $this->billetId);
        self::assertSame(TicketStatus::STATUT_REPORTE->value, $origine->getStatut());

        $nouveauId = (int) ($this->reponseJson()['id'] ?? 0);
        self::assertNotSame($this->billetId, $nouveauId, 'le report crée un billet, il ne déplace pas l\'ancien');

        $nouveau = $this->relire(Ticket::class, $nouveauId);
        self::assertSame(TicketStatus::STATUT_VALIDE->value, $nouveau->getStatut());
        self::assertSame(
            $this->billetId,
            $nouveau->getTicketOrigine()?->getId(),
            'le chaînage distingue un report d\'une vente ordinaire'
        );
    }

    #[Test]
    #[TestDox("Un report sur un siège déjà vendu est refusé")]
    public function reportSurSiegeOccupe(): void
    {
        $cible = $this->relire(\App\Entity\Voyage::class, $this->voyageCibleId);
        $this->scenario->billet($this->reseau, $cible, siege: 3, de: 'Abidjan', a: 'Korhogo');

        $this->desister([
            'mode' => 'REPORT',
            'motif' => 'Report',
            'voyage' => '/api/voyages/' . $this->voyageCibleId,
            'siege' => '/api/sieges/' . $this->siegeCibleId,
        ]);

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
        self::assertSame(
            TicketStatus::STATUT_VALIDE->value,
            $this->relire(Ticket::class, $this->billetId)->getStatut(),
            'le billet d\'origine reste valide tant que le report n\'aboutit pas'
        );
    }

    #[Test]
    #[TestDox("Un report ne passe pas par-dessus une réservation qui tient la dernière place")]
    public function reportRefuseQuandLaCapaciteEstTenueParUneReservation(): void
    {
        /*
            Le cas subtil : le siège visé est LIBRE au sens des billets, mais la capacité restante
            est déjà retenue par des réservations — qui, elles, ne réservent pas de siège précis.
            Sans la garde de capacité, ce report déposséderait un client ayant payé.
        */
        $petit = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 1),
            depart: new DateTimeImmutable('+2 days')
        );
        $this->scenario->reservation($this->reseau, $petit, 'Abidjan', 'Korhogo');

        $this->desister([
            'mode' => 'REPORT',
            'motif' => 'Report',
            'voyage' => '/api/voyages/' . $petit->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($petit, 1)->getId(),
        ]);

        self::assertGreaterThanOrEqual(
            400,
            $this->client->getResponse()->getStatusCode(),
            'la seule place est tenue par la réservation : le report doit être refusé'
        );
    }

    #[Test]
    #[TestDox("Le désistement reste ouvert tant que le car est À QUAI à la gare de montée")]
    public function desistementCarAQuai(): void
    {
        /*
            Le car est arrivé à Abidjan sans en repartir : le passager est encore au guichet, face à
            l'agent. Fermer ici était incohérent — au même instant la VENTE reste ouverte, et le
            commercial à bord peut corriger. On s'aligne sur 'monteeDepassee'.
        */
        $billet = $this->relire(Ticket::class, $this->billetId);
        $voyage = $billet->getVoyage();
        $voyage->setGarecourante($this->reseau->gare('Abidjan'));
        $this->em->flush();

        $this->desister(['mode' => 'ANNULATION', 'motif' => 'Le client renonce avant de monter']);

        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("Le désistement est fermé une fois le car REPARTI de la gare de montée")]
    public function desistementApresDepartDuCar(): void
    {
        $billet = $this->relire(Ticket::class, $this->billetId);
        $voyage = $billet->getVoyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-1 hour'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $this->desister(['mode' => 'ANNULATION', 'motif' => 'Trop tard']);

        // Service en cours : le passager est parti avec le car.
        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    #[TestDox("Un mode inconnu est rejeté par la validation")]
    public function modeInvalide(): void
    {
        $this->desister(['mode' => 'PEUT-ETRE', 'motif' => 'x']);

        $this->assertStatut(422);
    }

    // -------------------------------------------------- QUI a le droit de rembourser -----------

    #[Test]
    #[TestDox('La permission MODIFIER ne suffit plus : le remboursement exige DESISTER')]
    public function modifierNOuvrePlusLeRemboursement(): void
    {
        // Profil « corrige les saisies » : il peut rectifier un nom, pas ouvrir la caisse.
        $correcteur = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'MODIFIER']]
        );

        $this->requete('PATCH', '/api/tickets/' . $this->billetId . '/desister', $correcteur, [
            'mode' => 'ANNULATION',
            'motif' => 'Tentative sans la permission dédiée',
        ]);

        $this->assertStatut(403);
        self::assertSame(
            TicketStatus::STATUT_VALIDE->value,
            $this->relire(Ticket::class, $this->billetId)->getStatut()
        );
    }

    #[Test]
    #[TestDox('Le commercial à bord est refusé même muni de la permission DESISTER')]
    public function commercialRefuseMemeAvecLaPermission(): void
    {
        /*
            Défense en profondeur. Les permissions sont configurables par le super admin, et la
            migration de reprise a justement recopié DESISTER partout où MODIFIER existait : la règle
            métier ne peut donc pas reposer sur la seule configuration des rôles.

            Rattaché à Abidjan, il franchit le contrôle « gare émettrice » — c'est exactement par là
            qu'il annulait des ventes du guichet (constaté : HTTP 200, billet ANNULE).
        */
        $commercial = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'MODIFIER', 'DESISTER']]
        );
        $billet = $this->relire(Ticket::class, $this->billetId);
        $billet->getVoyage()->setCommercial($commercial);
        $this->em->flush();

        $this->requete('PATCH', '/api/tickets/' . $this->billetId . '/desister', $commercial, [
            'mode' => 'ANNULATION',
            'motif' => 'Le vendeur à bord tente un remboursement',
        ]);

        $this->assertStatut(400);
        self::assertSame(
            TicketStatus::STATUT_VALIDE->value,
            $this->relire(Ticket::class, $this->billetId)->getStatut(),
            'le remboursement est une opération de gare : le billet ne doit pas bouger'
        );
    }

    #[Test]
    #[TestDox('Un guichetier muni de DESISTER rembourse normalement')]
    public function guichetierAvecDesisterRembourse(): void
    {
        $guichetier = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'CREER', 'MODIFIER', 'DESISTER']]
        );

        $this->requete('PATCH', '/api/tickets/' . $this->billetId . '/desister', $guichetier, [
            'mode' => 'ANNULATION',
            'motif' => 'Le client renonce',
        ]);

        $this->assertStatut(200);
        self::assertSame(
            TicketStatus::STATUT_ANNULE->value,
            $this->relire(Ticket::class, $this->billetId)->getStatut()
        );
    }

    /** @param array<string, mixed> $corps */
    private function desister(array $corps): void
    {
        $this->requete('PATCH', '/api/tickets/' . $this->billetId . '/desister', $this->agent, $corps);
    }
}

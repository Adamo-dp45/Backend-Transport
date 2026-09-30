<?php

namespace App\Tests\Api;

use App\Domain\Enum\SessioncaisseStatut;
use App\Domain\Enum\TicketStatus;
use App\Entity\Sessioncaisse;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LA CLÔTURE d'une caisse (palier 2) : le tiroir compté face à ce qu'il aurait dû contenir.
 *
 * Deux règles se jouent ici, et elles se ressemblent assez pour qu'on les confonde :
 *  - le statut NE FILTRE PAS le théorique — l'argent d'une vente annulée est bien passé par le
 *    tiroir, et c'est la ligne de remboursement qui l'en ressort ;
 *  - la corbeille, SI — elle dit que la ligne n'aurait jamais dû être saisie, donc que rien n'est
 *    entré. La garder ferait porter à l'agent un manquant qu'il n'a pas commis.
 *
 * Les états sont posés directement en base : ce qui est éprouvé ici est le CALCUL du théorique,
 * pas le geste qui mène à ces états — le désistement et la corbeille ont leurs propres tests.
 */
final class ClotureCaisseTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarifbagage($this->reseau->entreprise, poidsmin: 0, poidsmax: 30, montant: 2000);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            depart: new DateTimeImmutable('+3 hours')
        );

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            [
                'Ticket' => ['VOIR', 'CREER'],
                'Bagage' => ['VOIR', 'CREER'],
                'Sessioncaisse' => ['VOIR', 'CREER', 'CLOTURER'],
            ]
        );
    }

    #[Test]
    #[TestDox("Le théorique est la somme des pièces encaissées, fonds d'ouverture compris")]
    public function theoriqueEgaleLaSommeDesPieces(): void
    {
        $this->ouvrir(fonds: 5000);
        $this->vendre(siege: 1);
        $this->vendre(siege: 2);

        $this->cloturer(montantcompte: 35000);

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(30000, $session->getTotalbillets(), 'deux billets à 15 000');
        self::assertSame(
            35000,
            $session->getMontanttheorique(),
            'le fonds de 5 000 est PHYSIQUEMENT dans le tiroir : il fait partie du théorique'
        );
        self::assertSame(0, $session->getEcart());
        self::assertNull($session->getMotifecart(), 'pas de motif quand rien ne cloche');
        self::assertSame(SessioncaisseStatut::CLOTUREE->value, $session->getStatut());
        self::assertNotNull($session->getDatefin());
    }

    #[Test]
    #[TestDox('Un écart sans motif est refusé, dans les DEUX sens')]
    public function ecartSansMotifRefuse(): void
    {
        $this->ouvrir();
        $this->vendre(siege: 1);

        $this->cloturer(montantcompte: 14000);
        $this->assertStatut(400);
        self::assertStringContainsString('indiquez un motif', $this->messageErreur(), 'manquant de 1 000');
        self::assertStringNotContainsString(
            '1000',
            str_replace(' ', '', $this->messageErreur()),
            "le message ne CHIFFRE pas l'écart : l'agent peut encore corriger sa saisie, et le lui "
            . "annoncer lui offrirait de faire tomber le compte juste pour repartir sans motif"
        );

        $this->cloturer(montantcompte: 16000);
        $this->assertStatut(400);
        self::assertStringContainsString(
            'indiquez un motif',
            $this->messageErreur(),
            "un EXCÉDENT s'explique aussi mal qu'un manquant : monnaie mal rendue, ou vente encaissée sans être saisie"
        );

        self::assertSame(
            SessioncaisseStatut::OUVERTE->value,
            $this->caisse()->getStatut(),
            'une clôture refusée ne ferme pas la caisse'
        );
    }

    #[Test]
    #[TestDox("Un écart motivé est constaté et signé, l'écart restant SIGNÉ")]
    public function ecartMotiveEstConstate(): void
    {
        $this->ouvrir();
        $this->vendre(siege: 1);

        $this->cloturer(montantcompte: 14000, motifecart: 'Billet de 1 000 rendu en trop à un client');

        $this->assertStatut(200);
        $session = $this->caisse();
        self::assertSame(-1000, $session->getEcart(), 'un manquant est NÉGATIF : le signe porte le sens');
        self::assertSame('Billet de 1 000 rendu en trop à un client', $session->getMotifecart());
    }

    #[Test]
    #[TestDox("Le théorique est FIGÉ : une correction après la clôture ne le déplace plus")]
    public function theoriqueFigeApresCloture(): void
    {
        $this->ouvrir();
        $billet = $this->vendre(siege: 1);
        $this->cloturer(montantcompte: 15000);
        $this->assertStatut(200);

        // Le prix change après coup — correction de saisie, annulation tardive, peu importe.
        $this->em->getRepository(Ticket::class)->find($billet)->setPrix(9000);
        $this->em->flush();

        $session = $this->caisse();
        $this->em->refresh($session);
        self::assertSame(
            15000,
            $session->getMontanttheorique(),
            "la clôture est une pièce opposable : l'écart signé ce soir-là ne doit pas bouger demain"
        );
    }

    #[Test]
    #[TestDox("Une vente ANNULÉE reste dans le théorique, une vente en CORBEILLE en sort")]
    public function statutNeFiltrePasMaisCorbeilleSi(): void
    {
        $this->ouvrir();
        $annule = $this->vendre(siege: 1);
        $corbeille = $this->vendre(siege: 2);

        $depot = $this->em->getRepository(Ticket::class);
        $depot->find($annule)->setStatut(TicketStatus::STATUT_ANNULE->value);
        $depot->find($corbeille)->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->cloturer(montantcompte: 15000);

        $this->assertStatut(200);
        self::assertSame(
            15000,
            $this->caisse()->getTotalbillets(),
            "l'argent de la vente annulée est bien passé par le tiroir — c'est le remboursement qui l'en ressortira ; "
            . "celle mise en corbeille n'aurait jamais dû être saisie, donc rien n'est entré"
        );
    }

    #[Test]
    #[TestDox("Une caisse clôturée ne se re-clôture pas, et la vente suivante en ouvre une NOUVELLE")]
    public function pasDeReouverture(): void
    {
        $this->ouvrir();
        $this->vendre(siege: 1);
        $this->cloturer(montantcompte: 15000);
        $this->assertStatut(200);
        $premiere = $this->caisse()->getId();

        $this->cloturer(montantcompte: 15000);
        $this->assertStatut(400);
        self::assertStringContainsString('déjà clôturée', $this->messageErreur());

        $this->vendre(siege: 2);
        $caisses = $this->em->getRepository(Sessioncaisse::class)->findBy(['agent' => $this->agent->getId()]);
        self::assertCount(2, $caisses, 'reprendre la vente ouvre une NOUVELLE caisse');
        self::assertNotSame(
            $premiere,
            $this->caisseOuverte()?->getId(),
            "la clôture précédente reste intacte : l'index unique n'a pas bloqué la suivante"
        );
    }

    #[Test]
    #[TestDox("On n'ouvre pas deux caisses, et un acteur sans gare n'en ouvre aucune")]
    public function ouverturesRefusees(): void
    {
        /*
            !! CRÉÉ EN PREMIER, et ce n'est pas un choix de style. Symfony RÉINITIALISE le
            gestionnaire Doctrine dès qu'une requête se termine par une exception : les entités que
            le test tient en main sont alors DÉTACHÉES. Construire cet utilisateur après les deux
            refus ci-dessous ferait échouer sa création sur « A new entity was found through the
            relationship User#entreprise » — une erreur qui ne désigne ni la cause ni le coupable.
        */
        $central = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: null),
            ['Sessioncaisse' => ['VOIR', 'CREER']]
        );

        $this->ouvrir(fonds: 5000);
        $this->assertStatut(201);

        $this->ouvrir(fonds: 3000);
        $this->assertStatut(400);
        self::assertStringContainsString('déjà ouverte', $this->messageErreur());

        $this->requete('POST', '/api/sessioncaisses', $central, ['fondsouverture' => 1000]);
        $this->assertStatut(400);
        self::assertStringContainsString('aucune gare', $this->messageErreur());
    }

    private function ouvrir(int $fonds = 0): void
    {
        $this->requete('POST', '/api/sessioncaisses', $this->agent, ['fondsouverture' => $fonds]);
    }

    private function cloturer(int $montantcompte, ?string $motifecart = null): void
    {
        $corps = ['montantcompte' => $montantcompte];
        if ($motifecart !== null) {
            $corps['motifecart'] = $motifecart;
        }

        $this->requete(
            'PATCH',
            '/api/sessioncaisses/' . $this->caisse()->getId() . '/cloturer',
            $this->agent,
            $corps
        );
    }

    private function vendre(int $siege): int
    {
        $this->requete('POST', '/api/tickets', $this->agent, [
            'voyage' => '/api/voyages/' . $this->voyage->getId(),
            'siege' => '/api/sieges/' . $this->scenario->siege($this->voyage, $siege)->getId(),
            'gare' => '/api/gares/' . $this->reseau->gare('Abidjan')->getId(),
            'garedescente' => '/api/gares/' . $this->reseau->gare('Korhogo')->getId(),
            'nomclient' => 'Client de test',
            'contactclient' => '+225 07 00 00 00 01',
        ]);
        $this->assertStatut(201);

        return $this->reponseJson()['id'];
    }

    /** La caisse de l'agent — la plus récente, pour que les tests à deux caisses visent la bonne. */
    private function caisse(): Sessioncaisse
    {
        $session = $this->em->getRepository(Sessioncaisse::class)->findOneBy(
            ['agent' => $this->agent->getId()],
            ['id' => 'DESC']
        );
        self::assertNotNull($session, 'aucune caisse pour cet agent');

        return $session;
    }

    private function caisseOuverte(): ?Sessioncaisse
    {
        return $this->em->getRepository(Sessioncaisse::class)->findOneBy([
            'agent' => $this->agent->getId(),
            'statut' => SessioncaisseStatut::OUVERTE->value,
        ]);
    }

    private function messageErreur(): string
    {
        $r = $this->reponseJson();

        return (string) ($r['detail'] ?? $r['description'] ?? $r['hydra:description'] ?? '');
    }
}

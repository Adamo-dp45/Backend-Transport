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
 * UNE ASSOCIATION DOCTRINE NE CONNAÎT PAS LA CORBEILLE.
 *
 * Aucun filtre SQL de softdelete n'est enregistré dans ce projet : la suppression logique est tenue
 * par les EXTENSIONS d'API Platform, qui s'appliquent aux collections de RESSOURCE
 * (`/api/tickets?…`) et JAMAIS à un `OneToMany` hydraté par Doctrine. Exposer `Voyage::$tickets` tel
 * quel faisait donc apparaître sur la fiche d'un voyage des billets mis à la corbeille — invisibles
 * partout ailleurs, `getTicketsCount()` compris, qui filtre lui. Le même écran affichait deux
 * nombres différents pour la même chose, et le plus gros était le faux.
 *
 * Trois populations à ne pas confondre, et ce fichier les sépare :
 *  - SUPPRIMÉ (corbeille) → absent de la fiche, absent des compteurs ;
 *  - ANNULÉ (désistement) → PRÉSENT sur la fiche (c'est l'histoire du départ), hors compteurs ;
 *  - VALIDE → présent partout.
 */
final class FicheVoyageTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 20)
        );
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    /** @return array<string, mixed> la fiche du voyage telle que l'API la sert */
    private function fiche(): array
    {
        /*
            REFRESH OBLIGATOIRE, et ce n'est pas un détail de plomberie : le test et la requête HTTP
            partagent l'EntityManager (cf. 'relire()'). Le '$voyage' construit dans le setUp porte donc
            une collection 'tickets' DÉJÀ INITIALISÉE à vide, et le sérialiseur filtrerait en mémoire sur
            cet ensemble vide — la fiche rendrait '[]' quoi qu'on ait inséré. On chasse l'objet périmé
            de l'identity map pour que la requête relise la base, comme le ferait un vrai appel.
        */
        $this->em->refresh($this->voyage);

        $this->requete('GET', '/api/voyages/' . $this->voyage->getId(), $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson();
    }

    #[Test]
    #[TestDox("Un billet en corbeille ne figure pas sur la fiche du voyage")]
    public function billetEnCorbeilleAbsentDeLaFiche(): void
    {
        $garde = $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo');
        $corbeille = $this->scenario->billet($this->reseau, $this->voyage, 2, 'Abidjan', 'Bouaké');
        $corbeille->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();

        $codes = array_column($this->fiche()['tickets'] ?? [], 'codeticket');

        self::assertContains($garde->getCodeticket(), $codes);
        self::assertNotContains(
            $corbeille->getCodeticket(),
            $codes,
            'un billet en corbeille est invisible partout ailleurs : il doit l\'être ici aussi'
        );
    }

    #[Test]
    #[TestDox('Un billet DÉSISTÉ reste sur la fiche, mais hors du compteur')]
    public function billetDesisteVisibleMaisHorsCompteur(): void
    {
        /*
            La distinction qui compte. Un désistement fait partie de l'histoire du départ — le chef de
            gare doit pouvoir le lire —, mais il n'est ni une vente ni une place tenue. La fiche le
            MONTRE et les compteurs l'IGNORENT ; c'est à l'écran d'afficher son statut pour qu'on ne le
            prenne pas pour un passager.
        */
        $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo');
        $desiste = $this->scenario->billet(
            $this->reseau, $this->voyage, 2, 'Abidjan', 'Bouaké',
            statut: TicketStatus::STATUT_ANNULE
        );

        $fiche = $this->fiche();
        $codes = array_column($fiche['tickets'] ?? [], 'codeticket');

        self::assertContains($desiste->getCodeticket(), $codes, 'le désistement reste lisible');
        self::assertSame(1, $fiche['ticketsCount'], 'mais un seul billet COMPTE');
    }

    #[Test]
    #[TestDox('Bagages, courriers et personnel affectés suivent la même règle')]
    public function lesAutresCollectionsAussi(): void
    {
        $billet = $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo');

        $bagageVu = $this->scenario->bagage($this->reseau, $billet);
        $bagageCorbeille = $this->scenario->bagage($this->reseau, $billet);
        $bagageCorbeille->setDeletedAt(new DateTimeImmutable());

        $courrierVu = $this->scenario->courrier($this->reseau, 'Abidjan', 'Korhogo');
        $courrierVu->setVoyage($this->voyage);
        $courrierCorbeille = $this->scenario->courrier($this->reseau, 'Abidjan', 'Korhogo');
        $courrierCorbeille->setVoyage($this->voyage)->setDeletedAt(new DateTimeImmutable());

        $this->em->flush();

        $fiche = $this->fiche();

        $bagages = array_column($fiche['bagages'] ?? [], 'codebagage');
        self::assertContains($bagageVu->getCodebagage(), $bagages);
        self::assertNotContains($bagageCorbeille->getCodebagage(), $bagages);
        self::assertSame(1, $fiche['bagagesCount'], 'le compteur et la liste disent la même chose');

        $courriers = array_column($fiche['courriers'] ?? [], 'codecourrier');
        self::assertContains($courrierVu->getCodecourrier(), $courriers);
        self::assertNotContains($courrierCorbeille->getCodecourrier(), $courriers);
        self::assertSame(1, $fiche['courriersCount']);
    }
}

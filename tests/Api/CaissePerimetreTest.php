<?php

namespace App\Tests\Api;

use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * QUI VOIT ET QUI FERME une caisse (palier 3).
 *
 * Une caisse porte ce qu'une personne a encaissé et ce qui lui manque le soir : laisser un
 * guichetier lire celles de ses collègues donnerait à voir, de tout l'effectif d'une gare, qui est
 * en difficulté — une information de gestion, pas d'exploitation.
 *
 * DEUX barrières distinctes, et il faut les deux : 'CaisseScopeExtension' borne ce qu'on LIT,
 * 'CaisseGuard' borne ce qu'on FERME. La permission 'CLOTURER', elle, ne connaît pas l'objet visé —
 * elle dit « il sait clôturer », jamais « celle-ci ».
 */
final class CaissePerimetreTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $agent;

    private User $collegue;

    private User $chefDeGare;

    private User $agentAutreGare;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);

        $permissions = ['Sessioncaisse' => ['VOIR', 'CREER', 'CLOTURER']];

        $this->agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            $permissions
        );
        $this->collegue = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            $permissions
        );
        $this->agentAutreGare = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            $permissions
        );
        $this->chefDeGare = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan'),
            roles: ['ROLE_ADMIN_GARE']
        );
    }

    #[Test]
    #[TestDox('Un agent ne voit que SES caisses, et celle du collègue est introuvable')]
    public function agentNeVoitQueLesSiennes(): void
    {
        $this->ouvrirPour($this->agent);
        $caisseDuCollegue = $this->ouvrirPour($this->collegue);

        $this->requete('GET', '/api/sessioncaisses', $this->agent);
        $this->assertStatut(200);
        self::assertSame(
            1,
            $this->reponseJson()['totalItems'] ?? null,
            'la caisse du collègue de la même gare ne doit pas figurer dans sa liste'
        );

        $this->requete('GET', '/api/sessioncaisses/' . $caisseDuCollegue, $this->agent);
        self::assertSame(
            404,
            $this->client->getResponse()->getStatusCode(),
            "404 et non 403 : l'extension s'applique AUSSI à l'item, donc l'objet est introuvable "
            . "avant même que la sécurité ne parle — il ne révèle pas son existence"
        );
    }

    #[Test]
    #[TestDox('Un agent ne clôture pas la caisse de son collègue')]
    public function agentNeCloturePasCelleDuCollegue(): void
    {
        $caisseDuCollegue = $this->ouvrirPour($this->collegue);

        $this->requete(
            'PATCH',
            '/api/sessioncaisses/' . $caisseDuCollegue . '/cloturer',
            $this->agent,
            ['montantcompte' => 0]
        );

        self::assertContains(
            $this->client->getResponse()->getStatusCode(),
            [403, 404],
            "la permission CLOTURER dit « il sait clôturer », pas « celle-ci » : sans la garde, un "
            . "guichetier figerait des totaux qu'il n'a pas comptés"
        );
        self::assertSame(
            'OUVERTE',
            $this->em->getRepository(Sessioncaisse::class)->find($caisseDuCollegue)?->getStatut()
        );
    }

    #[Test]
    #[TestDox("Le chef de gare voit les caisses de SA gare et peut les clôturer")]
    public function chefDeGareVoitEtCloture(): void
    {
        $this->ouvrirPour($this->agent);
        $caisseDuCollegue = $this->ouvrirPour($this->collegue);
        $this->ouvrirPour($this->agentAutreGare);

        $this->requete('GET', '/api/sessioncaisses', $this->chefDeGare);
        $this->assertStatut(200);
        self::assertSame(
            2,
            $this->reponseJson()['totalItems'] ?? null,
            'les deux caisses d\'Abidjan, pas celle de Bouaké : surveiller SA gare est son métier, '
            . 'et GareScopeExtension borne le reste'
        );

        $this->requete(
            'PATCH',
            '/api/sessioncaisses/' . $caisseDuCollegue . '/cloturer',
            $this->chefDeGare,
            ['montantcompte' => 0]
        );
        $this->assertStatut(200);
        self::assertSame(
            'CLOTUREE',
            $this->em->getRepository(Sessioncaisse::class)->find($caisseDuCollegue)?->getStatut(),
            "une caisse se ferme parfois sans son titulaire — parti, malade, distrait : un constat "
            . "remis au lendemain ne vaut plus rien"
        );
    }

    #[Test]
    #[TestDox('Sans la permission CLOTURER, on ne ferme pas même sa propre caisse')]
    public function sansPermissionPasDeCloture(): void
    {
        $sansDroit = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Sessioncaisse' => ['VOIR', 'CREER']]
        );
        $caisse = $this->ouvrirPour($sansDroit);

        $this->requete(
            'PATCH',
            '/api/sessioncaisses/' . $caisse . '/cloturer',
            $sansDroit,
            ['montantcompte' => 0]
        );

        $this->assertStatut(403);
    }

    private function ouvrirPour(User $user): int
    {
        $this->requete('POST', '/api/sessioncaisses', $user, ['fondsouverture' => 0]);
        $this->assertStatut(201);

        return $this->reponseJson()['id'];
    }
}

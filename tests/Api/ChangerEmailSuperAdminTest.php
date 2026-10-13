<?php

namespace App\Tests\Api;

use App\Domain\Service\ActiviteLogger;
use App\Entity\Activite;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LE SUPER ADMIN CHANGE L'E-MAIL D'UN UTILISATEUR — la porte de sortie de la règle du 06/10/2026.
 *
 * L'e-mail n'est plus modifiable en self-service (cf. 'ProfilEmailTest'). L'admin d'une compagnie
 * change celui de ses agents par 'PATCH /users/{id}', mais cette route LUI EST FERMÉE pour
 * lui-même : il restait enfermé derrière une adresse qu'il ne pouvait plus corriger.
 *
 * QUATRE CHOSES QUE CE TEST TIENT, et dont trois ne se voient sur aucun code de statut :
 *
 *  1. LA PORTE EST ÉTROITE. Elle n'accepte qu'un e-mail. Ouvrir le 'PATCH' général au super admin
 *     lui aurait donné du même coup les rôles, l'entreprise et la gare — ce que sa garde protège.
 *  2. ELLE EST RÉSERVÉE AU SUPER ADMIN. Un admin de compagnie passe par la route générale ; lui
 *     donner celle-ci ne changerait rien pour ses agents et contournerait la règle pour lui-même.
 *  3. LE CHANGEMENT EST TRACÉ, AVEC L'ANCIENNE ADRESSE. Et c'est là que gît le piège mesuré :
 *     'ActiviteLogger' se scope sur l'entreprise de l'ACTEUR, or LE SUPER ADMIN N'EN A AUCUNE
 *     (vérifié en base : 'entreprise_id' nul). La première version sortait de 'log()' en silence —
 *     geste accepté, 200 rendu, journal vide. La trace va donc au journal de LA CIBLE.
 *  4. UNE ADRESSE DÉJÀ PRISE EST REFUSÉE AVEC SA RAISON. La colonne porte 'UNIQ_IDENTIFIER_EMAIL' :
 *     sans contrôle PHP, le doublon ressortait en 500 SQL illisible.
 */
final class ChangerEmailSuperAdminTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);

        /*
            SANS ENTREPRISE, comme en production — c'est tout l'intérêt. Lui en donner une ferait
            passer le test de la trace pour la mauvaise raison : elle s'écrirait via le scope de
            l'acteur, et le défaut réel (super admin hors entreprise, journal muet) resterait
            invisible.
        */
        $this->superAdmin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_SUPER_ADMIN']);
        $this->superAdmin->setEntreprise(null);
        $this->em->flush();
    }

    #[Test]
    #[TestDox("Le super admin change l'e-mail d'un administrateur de compagnie")]
    public function leSuperAdminChangeLEmail(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        $this->requete('PATCH', '/api/users/' . $admin->getId() . '/email', $this->superAdmin, [
            'email' => 'direction.corrigee@example.test',
        ]);
        $this->assertStatut(200);

        self::assertSame(
            'direction.corrigee@example.test',
            $this->relire(User::class, (int) $admin->getId())->getEmail(),
            "c'est le seul chemin qui existe pour l'adresse d'un admin : ni son profil (fermé), ni "
            . "sa propre gestion d'utilisateurs (la garde du PATCH général le vise aussi)"
        );
    }

    #[Test]
    #[TestDox("La trace part au journal de la CIBLE : le super admin n'a aucune entreprise")]
    public function laTracePartAuJournalDeLaCible(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $ancienne = (string) $admin->getEmail();

        $this->requete('PATCH', '/api/users/' . $admin->getId() . '/email', $this->superAdmin, [
            'email' => 'tracee@example.test',
        ]);
        $this->assertStatut(200);

        $trace = $this->em->getRepository(Activite::class)
            ->findOneBy(['type' => ActiviteLogger::USER_EMAIL_MODIFIE, 'cibleid' => $admin->getId()]);

        self::assertNotNull(
            $trace,
            "AUCUNE TRACE = LE DÉFAUT MESURÉ. 'ActiviteLogger' sort sans écrire quand l'acteur n'a "
            . "pas d'entreprise, et le super admin n'en a pas : le geste le plus sensible du module "
            . "rendait 200 en ne journalisant rien"
        );
        self::assertSame(
            (int) $this->reseau->entreprise->getId(),
            $trace->getIdentreprise(),
            "le journal de la COMPAGNIE, pas celui de l'exploitant : c'est l'admin dont l'adresse "
            . "change qui doit pouvoir le constater"
        );
        self::assertStringContainsString(
            $ancienne,
            (string) $trace->getLibelle(),
            "l'ANCIENNE adresse est consignée — sans elle on constate le changement sans pouvoir "
            . "revenir dessus, alors que celui qui reçoit les liens de réinitialisation tient le compte"
        );
    }

    #[Test]
    #[TestDox("La porte n'accepte que l'e-mail : ni rôles, ni entreprise, ni gare")]
    public function laPorteNAcceptePasAutreChose(): void
    {
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        $this->requete('PATCH', '/api/users/' . $agent->getId() . '/email', $this->superAdmin, [
            'email' => 'agent.corrige@example.test',
            'roles' => ['ROLE_ADMIN'],
        ]);
        $this->assertStatut(200);

        $relu = $this->relire(User::class, (int) $agent->getId());
        self::assertSame('agent.corrige@example.test', $relu->getEmail());
        self::assertSame(
            [],
            array_values(array_diff($relu->getRoles(), ['ROLE_USER'])),
            "les rôles envoyés dans le même corps sont IGNORÉS. C'est la raison d'être de cette "
            . "route : ouvrir le PATCH général au super admin lui aurait donné les rôles avec l'e-mail"
        );
    }

    #[Test]
    #[TestDox("Un admin de compagnie n'a pas accès à cette route")]
    public function unAdminNAPasAccesALaRoute(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $origine = (string) $agent->getEmail();

        $this->requete('PATCH', '/api/users/' . $agent->getId() . '/email', $admin, [
            'email' => 'detourne@example.test',
        ]);
        $this->assertStatut(403);

        self::assertSame(
            $origine,
            $this->relire(User::class, (int) $agent->getId())->getEmail(),
            "l'admin dispose déjà du PATCH général pour ses agents. Lui ouvrir celle-ci ne lui "
            . "donnerait qu'une chose de plus : changer SON propre e-mail, ce que la règle ferme"
        );
    }

    #[Test]
    #[TestDox('Une adresse déjà prise est refusée avec sa raison, pas en 500')]
    public function uneAdresseDejaPriseEstRefusee(): void
    {
        $premier = $this->scenario->utilisateur($this->reseau->entreprise);
        $second = $this->scenario->utilisateur($this->reseau->entreprise);
        $origine = (string) $second->getEmail();

        $this->requete('PATCH', '/api/users/' . $second->getId() . '/email', $this->superAdmin, [
            'email' => (string) $premier->getEmail(),
        ]);
        $this->assertStatut(400);

        self::assertSame(
            $origine,
            $this->relire(User::class, (int) $second->getId())->getEmail(),
            "la colonne porte 'UNIQ_IDENTIFIER_EMAIL' : sans ce contrôle en PHP, le doublon "
            . "ressortait en 500 SQL. Un changement d'identifiant de connexion mérite de dire "
            . "pourquoi il est refusé"
        );
    }
}

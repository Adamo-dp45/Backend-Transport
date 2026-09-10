<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Le repère 'gerable' : qui peut gérer qui, tel que le SERVEUR le décide.
 *
 * 'UserManagementGuard' porte la règle depuis toujours, mais elle était REDITE côté front — un
 * « miroir » complet dans le tableau des utilisateurs, un reflet INCOMPLET dans la fiche, où
 * l'interdiction de se gérer soi-même avait été oubliée. On se proposait donc « Modifier le profil
 * et les rôles » sur sa propre fiche, pour un refus à l'enregistrement.
 *
 * Ces tests fixent le contrat entre les deux : ce que 'gerable' annonce est exactement ce que
 * l'écriture acceptera.
 */
final class PerimetreGestionUtilisateurTest extends ApiTestCase
{
    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
    }

    #[Test]
    #[TestDox('Personne ne se voit gérable soi-même, quel que soit son niveau')]
    public function personneNeSeGereSoiMeme(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        $this->requete('GET', '/api/users/' . $admin->getId(), $admin);

        $this->assertStatut(200);
        self::assertFalse(
            $this->reponseJson()['gerable'],
            'le profil personnel est une page à part : sa propre fiche n\'ouvre aucune gestion'
        );
    }

    #[Test]
    #[TestDox("Le repère annonce exactement ce que l'écriture acceptera")]
    public function leRepereAnnonceCeQueLEcritureAccepte(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        // Sur soi : annoncé non gérable, et l'écriture refuse.
        $this->requete('PATCH', '/api/users/' . $admin->getId(), $admin, ['nom' => 'Tentative']);
        self::assertGreaterThanOrEqual(
            400,
            $this->client->getResponse()->getStatusCode(),
            'l\'API doit refuser l\'auto-gestion — c\'est ce que « gerable: false » promet'
        );

        // Sur un agent de son entreprise : annoncé gérable, et l'écriture passe.
        $this->requete('GET', '/api/users/' . $agent->getId(), $admin);
        self::assertTrue($this->reponseJson()['gerable']);

        $this->requete('PATCH', '/api/users/' . $agent->getId(), $admin, ['nom' => 'Konan']);
        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox('Un administrateur d\'entreprise ne gère pas un autre administrateur')]
    public function unAdminNeGerePasUnAutreAdmin(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $autre = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        $this->requete('GET', '/api/users/' . $autre->getId(), $admin);

        $this->assertStatut(200);
        self::assertFalse(
            $this->reponseJson()['gerable'],
            'entre pairs, un administrateur relève du fondateur ou du super administrateur'
        );
    }

    #[Test]
    #[TestDox('Le FONDATEUR gère les autres administrateurs de sa compagnie')]
    public function leFondateurGereLesAdministrateurs(): void
    {
        /*
            Le fondateur nomme et rétrograde les administrateurs — 'PromouvoirUserProcessor' le lui
            réserve. Lui refuser l'édition de leur fiche revenait à séparer la décision de promotion
            de la gestion du compte promu.
        */
        $fondateur = $this->fondateur();
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        $this->requete('GET', '/api/users/' . $admin->getId(), $fondateur);
        $this->assertStatut(200);
        self::assertTrue($this->reponseJson()['gerable']);

        $this->requete('PATCH', '/api/users/' . $admin->getId(), $fondateur, ['nom' => 'Yao']);
        $this->assertStatut(200);
        self::assertSame('Yao', $this->relire(User::class, $admin->getId())->getNom());
    }

    #[Test]
    #[TestDox('Le fondateur ne gère ni lui-même, ni les comptes d\'une autre compagnie')]
    public function lesBornesDuFondateur(): void
    {
        $fondateur = $this->fondateur();

        // Lui-même : le profil personnel reste une page à part.
        $this->requete('GET', '/api/users/' . $fondateur->getId(), $fondateur);
        self::assertFalse($this->reponseJson()['gerable']);

        /*
            Une AUTRE compagnie : 'UserEntrepriseExtension' filtre aussi le chargement de l'item, si
            bien que la cible est introuvable — c'est cette isolation qui rend sûre l'ouverture faite
            au fondateur ci-dessus.
        */
        $concurrent = $this->scenario->entreprise('Sahel Voyages');
        $adminConcurrent = $this->scenario->utilisateur($concurrent, roles: ['ROLE_ADMIN']);

        $this->requete('PATCH', '/api/users/' . $adminConcurrent->getId(), $fondateur, ['nom' => 'Intrusion']);
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'hors périmètre : la cible n\'existe pas pour lui');
        self::assertNotSame('Intrusion', $this->relire(User::class, $adminConcurrent->getId())->getNom());
    }

    /** Le fondateur de la compagnie : ROLE_ADMIN et drapeau 'isFounder'. */
    private function fondateur(): User
    {
        $user = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $user->setIsFounder(true);
        $this->em->flush();

        return $user;
    }

    #[Test]
    #[TestDox('Le repère accompagne aussi la liste, pas seulement la fiche')]
    public function leRepereAccompagneLaListe(): void
    {
        // La liste et la fiche divergeaient : c'est le tableau qui portait la règle complète.
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));

        $this->requete('GET', '/api/users', $admin);
        $this->assertStatut(200);

        $lignes = $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];
        self::assertNotEmpty($lignes);

        foreach ($lignes as $ligne) {
            self::assertArrayHasKey('gerable', $ligne, 'chaque ligne porte son verdict');
            if ((int) $ligne['id'] === $admin->getId()) {
                self::assertFalse($ligne['gerable'], 'sa propre ligne n\'ouvre aucune action');
            }
        }
    }
}

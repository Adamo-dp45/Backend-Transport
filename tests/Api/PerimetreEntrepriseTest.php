<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'étanchéité entre compagnies — la garantie la plus lourde de conséquences de toute
 * l'application : une fuite ici expose les données commerciales d'un transporteur à un concurrent.
 *
 * Le filtre est POSÉ AUTOMATIQUEMENT par 'EntrepriseScopeExtension' sur toutes les collections et
 * tous les items. Ce caractère implicite est sa force (aucun contrôleur ne peut l'oublier) et son
 * danger : rien, à la lecture d'un endpoint, ne rappelle qu'il existe. Seul un test qui interroge
 * réellement l'API avec le jeton d'une autre compagnie peut le vérifier.
 *
 * Ces tests couvrent aussi le SOFT DELETE, appliqué par la même extension : un enregistrement
 * supprimé doit disparaître des listes sans disparaître de la base.
 */
final class PerimetreEntrepriseTest extends ApiTestCase
{
    private Reseau $alpha;

    private Reseau $beta;

    protected function setUp(): void
    {
        parent::setUp();

        // Deux compagnies concurrentes, aux gares volontairement homonymes : si un filtre manque,
        // le test doit échouer sur le NOMBRE de résultats, pas seulement sur leurs libellés.
        $this->alpha = $this->scenario->reseau(['Alpha Nord', 'Alpha Sud']);
        $this->beta = $this->scenario->reseau(['Beta Est', 'Beta Ouest']);
    }

    #[Test]
    #[TestDox("Un agent ne voit que les gares de sa compagnie")]
    public function collectionBorneeALaCompagnie(): void
    {
        $agent = $this->scenario->utilisateur($this->alpha->entreprise);

        $this->requete('GET', '/api/gares', $agent);

        $this->assertStatut(200);
        $libelles = $this->libellesRetournes();
        sort($libelles);

        self::assertSame(['Alpha Nord', 'Alpha Sud'], $libelles);
    }

    #[Test]
    #[TestDox("La fiche d'une gare d'une autre compagnie est introuvable, pas seulement interdite")]
    public function itemDUneAutreCompagnieEstIntrouvable(): void
    {
        $agent = $this->scenario->utilisateur($this->alpha->entreprise);
        $gareConcurrente = $this->beta->gare('Beta Est');

        $this->requete('GET', '/api/gares/' . $gareConcurrente->getId(), $agent);

        // 404 et non 403 : répondre « interdit » confirmerait au passage que l'identifiant existe.
        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Les voyages d'une autre compagnie n'apparaissent pas dans la liste")]
    public function voyagesBornesALaCompagnie(): void
    {
        $this->scenario->voyage($this->alpha, car: $this->scenario->car($this->alpha->entreprise, 10));
        $this->scenario->voyage($this->beta, car: $this->scenario->car($this->beta->entreprise, 10));
        $this->scenario->voyage($this->beta, car: $this->scenario->car($this->beta->entreprise, 10));

        $agent = $this->scenario->utilisateur($this->alpha->entreprise);
        $this->requete('GET', '/api/voyages', $agent);

        $this->assertStatut(200);
        self::assertCount(1, $this->reponseJson()['member'] ?? [], "un seul voyage appartient à Alpha");
    }

    #[Test]
    #[TestDox("Le super administrateur, hors périmètre entreprise, voit les données de toutes les compagnies")]
    public function superAdminVoitToutesLesCompagnies(): void
    {
        // Le super admin n'appartient à AUCUNE compagnie : plusieurs services en dépendent.
        $super = $this->scenario->utilisateur($this->alpha->entreprise, roles: ['ROLE_SUPER_ADMIN']);
        $super->setEntreprise(null);
        $this->em->flush();

        $this->requete('GET', '/api/gares', $super);

        $this->assertStatut(200);
        $libelles = $this->libellesRetournes();
        sort($libelles);

        self::assertSame(['Alpha Nord', 'Alpha Sud', 'Beta Est', 'Beta Ouest'], $libelles);
    }

    #[Test]
    #[TestDox("Un enregistrement supprimé en douceur sort des listes")]
    public function softDeleteRetireDesListes(): void
    {
        $agent = $this->scenario->utilisateur($this->alpha->entreprise);

        $this->alpha->gare('Alpha Sud')->setDeletedAt(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $this->requete('GET', '/api/gares', $agent);

        $this->assertStatut(200);
        self::assertSame(['Alpha Nord'], $this->libellesRetournes());
    }

    #[Test]
    #[TestDox("Un enregistrement supprimé en douceur reste inaccessible à l'unité")]
    public function softDeleteRetireLAccesUnitaire(): void
    {
        $agent = $this->scenario->utilisateur($this->alpha->entreprise);
        $gare = $this->alpha->gare('Alpha Sud');
        $gare->setDeletedAt(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $this->requete('GET', '/api/gares/' . $gare->getId(), $agent);

        $this->assertStatut(404);
    }

    #[Test]
    #[TestDox("Le super administrateur échappe aussi au filtre de suppression : c'est ce qui rend la corbeille possible")]
    public function superAdminVoitLesEnregistrementsSupprimes(): void
    {
        $super = $this->scenario->utilisateur($this->alpha->entreprise, roles: ['ROLE_SUPER_ADMIN']);
        $super->setEntreprise(null);
        $gare = $this->alpha->gare('Alpha Sud');
        $gare->setDeletedAt(new \DateTimeImmutable('-1 day'));
        $this->em->flush();

        $this->requete('GET', '/api/gares/' . $gare->getId(), $super);

        $this->assertStatut(200);
    }
}

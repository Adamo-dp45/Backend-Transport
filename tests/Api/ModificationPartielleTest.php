<?php

namespace App\Tests\Api;

use App\Entity\Approvisionnement;
use App\Entity\Depannage;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UN PATCH DOIT POUVOIR ÊTRE PARTIEL, ET CE QU'IL EXIGE DOIT SERVIR À QUELQUE CHOSE.
 *
 * Deux incohérences de contrat, du même genre et sur deux modules voisins. Ni l'une ni l'autre ne se
 * voyait : le frontend renvoie toujours la charge utile COMPLÈTE, si bien que personne n'avait jamais
 * emprunté le chemin cassé.
 *
 *  1. `DepannageInput` déclarait `lieudepannage` et `car` NON NULS (`NotBlank`/`NotNull`) — un PATCH
 *     partiel était donc refusé en 422 par la validation, alors que `handlePatch` était écrit pour le
 *     supporter (`$data->lieudepannage ?? $depannage->getLieudepannage()`). Le processeur acceptait ce
 *     que la validation interdisait.
 *  2. `ApprovisionnementInput` exigeait `fournisseur`… que `handlePatch` N'UTILISAIT PAS. Conséquence
 *     VISIBLE celle-là : le formulaire de modification propose de changer le fournisseur, l'écran
 *     confirme « modifié avec succès », et rien ne change. L'écran promettait ce que le serveur ne
 *     faisait pas.
 *
 * Les contraintes « obligatoire » vivent désormais dans le groupe de validation `creation`, que seul le
 * POST active. Ce qui reste dans `Default` s'applique aux deux : une valeur FOURNIE doit rester valide
 * (`NotBlank(allowNull: true)` refuse la chaîne vide sans exiger la présence).
 */
final class ModificationPartielleTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    // ───────────────────────────── DÉPANNAGE ─────────────────────────────

    #[Test]
    #[TestDox("Un dépannage se modifie PARTIELLEMENT : la description seule")]
    public function depannagePatchPartiel(): void
    {
        $id = $this->ouvrirDepannage();

        // Ni 'lieudepannage', ni 'car', ni 'typepanne', ni 'details' : le processeur sait les conserver.
        $this->requete('PATCH', '/api/depannages/' . $id, $this->admin, [
            'description' => 'Diagnostic précisé après démontage',
        ]);
        $this->assertStatut(200);

        $depannage = $this->relire(Depannage::class, $id);
        self::assertSame('Diagnostic précisé après démontage', $depannage->getDescription());
        self::assertSame('Bord de route', $depannage->getLieudepannage(), 'le lieu est conservé');
        self::assertSame(12000, (int) $depannage->getCouttotal(), 'et le coût avec');
    }

    #[Test]
    #[TestDox("À la CRÉATION, le car et le lieu restent obligatoires")]
    public function depannageCreationExigeLEssentiel(): void
    {
        // La contrepartie du test précédent : assouplir le PATCH ne doit rien ouvrir sur le POST.
        $this->requete('POST', '/api/depannages', $this->admin, [
            'description' => 'Sans lieu ni car',
            'details' => [['piece' => $this->pieceId(), 'quantite' => 1]],
        ]);

        $this->assertStatut(422);
    }

    #[Test]
    #[TestDox("Une création SANS la clé details est refusée, pas seulement avec un tableau vide")]
    public function creationSansCleDetailsRefusee(): void
    {
        /*
            'Count(min: 1)' IGNORE null — c'est le contrat de la contrainte. Il refuse donc '[]' mais
            laisse passer l'ABSENCE de la clé, et le processeur partait alors en erreur sur un 500. Trou
            PRÉEXISTANT, rendu atteignable par le passage des champs en nullable : il faut un 'NotNull'
            dans le groupe 'creation' à côté du 'Count'.
        */
        $this->requete('POST', '/api/depannages', $this->admin, [
            'lieudepannage' => 'Atelier',
            'car' => $this->scenario->car($this->reseau->entreprise, 30)->getId(),
            'typepanne' => $this->scenario->typepanne($this->reseau->entreprise)->getId(),
        ]);
        $this->assertStatut(422);

        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $this->scenario->fournisseur($this->reseau->entreprise)->getId(),
        ]);
        $this->assertStatut(422);
    }

    #[Test]
    #[TestDox("Un lieu VIDE est refusé même en modification : fourni, il doit rester valide")]
    public function depannageLieuVideRefuse(): void
    {
        $id = $this->ouvrirDepannage();

        /*
            La nuance que porte `NotBlank(allowNull: true)` : ABSENT veut dire « je n'y touche pas »,
            VIDE veut dire « efface-le » — et effacer le lieu d'un dépannage n'a pas de sens. Sans cette
            contrainte, l'assouplissement du PATCH aurait ouvert la porte à un lieu blanchi.
        */
        $this->requete('PATCH', '/api/depannages/' . $id, $this->admin, ['lieudepannage' => '']);

        $this->assertStatut(422);
    }

    // ─────────────────────────── APPROVISIONNEMENT ───────────────────────────

    #[Test]
    #[TestDox("Changer le FOURNISSEUR d'un approvisionnement le change vraiment")]
    public function approvisionnementChangeDeFournisseur(): void
    {
        $premier = $this->scenario->fournisseur($this->reseau->entreprise, 'Premier');
        $second = $this->scenario->fournisseur($this->reseau->entreprise, 'Second');
        $piece = $this->scenario->piece($this->reseau->entreprise);

        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $premier->getId(),
            'details' => [['piece' => $piece->getId(), 'quantite' => 5, 'prixunitaire' => 10000]],
        ]);
        $this->assertStatut(201);
        $id = (int) $this->reponseJson()['id'];

        /*
            LE BUG VISIBLE : le formulaire de modification propose un sélecteur de fournisseur, l'envoie,
            l'écran annonce « modifié avec succès »… et `handlePatch` n'y touchait pas. On changeait de
            fournisseur sans rien changer.
        */
        $this->requete('PATCH', '/api/approvisionnements/' . $id, $this->admin, [
            'fournisseur' => $second->getId(),
        ]);
        $this->assertStatut(200);

        self::assertSame(
            $second->getId(),
            $this->relire(Approvisionnement::class, $id)->getFournisseur()?->getId(),
            'le fournisseur envoyé doit être celui enregistré'
        );
    }

    #[Test]
    #[TestDox("Un approvisionnement se modifie sans renvoyer son fournisseur")]
    public function approvisionnementPatchPartiel(): void
    {
        $fournisseur = $this->scenario->fournisseur($this->reseau->entreprise);
        $piece = $this->scenario->piece($this->reseau->entreprise);

        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $fournisseur->getId(),
            'details' => [['piece' => $piece->getId(), 'quantite' => 5, 'prixunitaire' => 10000]],
        ]);
        $this->assertStatut(201);
        $id = (int) $this->reponseJson()['id'];

        // Seules les quantités changent : le fournisseur n'a pas à être répété pour autant.
        $this->requete('PATCH', '/api/approvisionnements/' . $id, $this->admin, [
            'details' => [['piece' => $piece->getId(), 'quantite' => 8, 'prixunitaire' => 10000]],
        ]);
        $this->assertStatut(200);

        self::assertSame(
            $fournisseur->getId(),
            $this->relire(Approvisionnement::class, $id)->getFournisseur()?->getId(),
            'le fournisseur est conservé'
        );
    }

    #[Test]
    #[TestDox("À la CRÉATION, un approvisionnement exige son fournisseur et une ligne")]
    public function approvisionnementCreationExigeLEssentiel(): void
    {
        $piece = $this->scenario->piece($this->reseau->entreprise);

        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'details' => [['piece' => $piece->getId(), 'quantite' => 5, 'prixunitaire' => 10000]],
        ]);
        $this->assertStatut(422);

        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $this->scenario->fournisseur($this->reseau->entreprise)->getId(),
            'details' => [],
        ]);
        $this->assertStatut(422);
    }

    // ───────────────────────────── outillage ─────────────────────────────

    private function pieceId(): int
    {
        return (int) $this->scenario->piece($this->reseau->entreprise, 'Filtre', 12000)->getId();
    }

    private function ouvrirDepannage(): int
    {
        $this->requete('POST', '/api/depannages', $this->admin, [
            'lieudepannage' => 'Bord de route',
            'description' => 'Panne moteur',
            'car' => $this->scenario->car($this->reseau->entreprise, 30)->getId(),
            'typepanne' => $this->scenario->typepanne($this->reseau->entreprise)->getId(),
            'details' => [['piece' => $this->pieceId(), 'quantite' => 1]],
        ]);
        $this->assertStatut(201);

        return (int) $this->reponseJson()['id'];
    }
}

<?php

namespace App\Tests\Api;

use App\Entity\Approvisionnement;
use App\Entity\Fournisseur;
use App\Entity\Piece;
use App\Entity\User;
use App\Repository\ApprovisionnementRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * `Approvisionnement::$couttotal` — L'ENTÊTE DIT TOUJOURS LA SOMME DE SES LIGNES.
 *
 * Champ ajouté le 28/09/2026 pour deux raisons :
 *
 *  1. la COHÉRENCE avec `Depannage::$couttotal`, qui portait déjà son coût sur l'entête ;
 *  2. des agrégats plus simples et un COMPTEUR juste : les requêtes passaient par
 *     `join('a.detailapprovisionnements')`, un INNER JOIN qui écarte un approvisionnement sans ligne.
 *     Attention à ne pas surestimer ce point — c'est l'erreur commise en le décrivant la première fois :
 *     sur le MONTANT la jointure ne faussait RIEN, la contribution d'un tel approvisionnement étant
 *     nulle (vérifié en la réintroduisant : le total ne bouge pas). Elle faussait le
 *     `COUNT(DISTINCT a.id)` de `achatsParFournisseur`.
 *
 * C'est un DÉRIVÉ STOCKÉ, l'exception que la doctrine maison n'accorde qu'aux pièces opposables. Le
 * risque d'un dérivé stocké est toujours le même : dériver de sa source sans que personne ne le voie.
 * D'où la sentinelle centrale ici — `retirerUneLigneFaitBaisserLEntete()` : une somme tenue
 * incrémentalement passerait tous les autres tests et ne tomberait QUE sur un retrait de ligne.
 */
final class CoutApprovisionnementTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private Fournisseur $fournisseur;

    private Piece $filtre;

    private Piece $pneu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->fournisseur = $this->scenario->fournisseur($this->reseau->entreprise);
        $this->filtre = $this->scenario->piece($this->reseau->entreprise, 'Filtre', 10000);
        $this->pneu = $this->scenario->piece($this->reseau->entreprise, 'Pneu', 50000);
    }

    /** @param list<array<string, mixed>> $details */
    private function creer(array $details): int
    {
        $this->requete('POST', '/api/approvisionnements', $this->admin, [
            'fournisseur' => $this->fournisseur->getId(),
            'details' => $details,
        ]);
        $this->assertStatut(201);

        return (int) $this->reponseJson()['id'];
    }

    /** @param list<array<string, mixed>> $details */
    private function modifier(int $id, array $details): void
    {
        $this->requete('PATCH', '/api/approvisionnements/' . $id, $this->admin, ['details' => $details]);
        $this->assertStatut(200);
    }

    private function entete(int $id): int
    {
        return (int) $this->relire(Approvisionnement::class, $id)->getCouttotal();
    }

    /** La somme des lignes, lue indépendamment de l'entête. */
    private function lignes(int $id): int
    {
        $total = 0;
        foreach ($this->relire(Approvisionnement::class, $id)->getDetailapprovisionnements() as $d) {
            $total += (int) $d->getCouttotal();
        }

        return $total;
    }

    private function coutEntreprise(): int
    {
        return (int) static::getContainer()->get(ApprovisionnementRepository::class)->coutTotal(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
            $this->reseau->identreprise()
        );
    }

    #[Test]
    #[TestDox("À la création, l'entête porte la somme de ses lignes")]
    public function aLaCreation(): void
    {
        $id = $this->creer([
            ['piece' => $this->filtre->getId(), 'quantite' => 3, 'prixunitaire' => 10000],
            ['piece' => $this->pneu->getId(), 'quantite' => 2, 'prixunitaire' => 50000],
        ]);

        self::assertSame(130000, $this->entete($id), '3 × 10 000 + 2 × 50 000');
        self::assertSame($this->lignes($id), $this->entete($id), 'entête et lignes disent la même chose');
        self::assertSame(130000, $this->coutEntreprise(), 'et le coût de l\'entreprise aussi');
    }

    #[Test]
    #[TestDox("Le champ est SERVI par l'API, pas seulement stocké")]
    public function servieParLApi(): void
    {
        $id = $this->creer([['piece' => $this->filtre->getId(), 'quantite' => 4, 'prixunitaire' => 10000]]);

        $this->requete('GET', '/api/approvisionnements/' . $id, $this->admin);
        $this->assertStatut(200);

        // Un champ recomposé mais non exposé obligerait le frontend à refaire la somme — c'est-à-dire à
        // garder le calcul qu'on vient de centraliser.
        self::assertSame(40000, (int) $this->reponseJson()['couttotal']);
    }

    #[Test]
    #[TestDox("Une quantité modifiée déplace l'entête d'autant")]
    public function quantiteModifiee(): void
    {
        $id = $this->creer([['piece' => $this->filtre->getId(), 'quantite' => 5, 'prixunitaire' => 10000]]);
        self::assertSame(50000, $this->entete($id));

        $this->modifier($id, [['piece' => $this->filtre->getId(), 'quantite' => 8, 'prixunitaire' => 10000]]);

        self::assertSame(80000, $this->entete($id), 'l\'entête suit la correction');
        self::assertSame($this->lignes($id), $this->entete($id));
        self::assertSame(80000, $this->coutEntreprise());
    }

    #[Test]
    #[TestDox("RETIRER une ligne fait BAISSER l'entête — la sentinelle du dérivé stocké")]
    public function retirerUneLigneFaitBaisserLEntete(): void
    {
        /*
            LE SEUL TEST QUI ATTRAPE UNE SOMME INCRÉMENTALE. Un processor qui ferait
            `couttotal += nouveau` au fil des opérations donnerait le bon chiffre à la création, à
            l'ajout et à l'augmentation d'une quantité — et garderait le montant d'une ligne SUPPRIMÉE.
            L'écart ne s'afficherait nulle part : l'entête resterait un nombre plausible, simplement
            trop grand, et le bénéfice trop bas. D'où le recalcul EN ENTIER à chaque écriture.
        */
        $id = $this->creer([
            ['piece' => $this->filtre->getId(), 'quantite' => 3, 'prixunitaire' => 10000],
            ['piece' => $this->pneu->getId(), 'quantite' => 2, 'prixunitaire' => 50000],
        ]);
        self::assertSame(130000, $this->entete($id));

        // Le pneu disparaît de la commande : il ne reste que les filtres.
        $this->modifier($id, [['piece' => $this->filtre->getId(), 'quantite' => 3, 'prixunitaire' => 10000]]);

        self::assertSame(30000, $this->entete($id), 'le pneu ne pèse plus');
        self::assertSame($this->lignes($id), $this->entete($id), 'entête et lignes restent d\'accord');
        self::assertSame(30000, $this->coutEntreprise());
    }

    #[Test]
    #[TestDox('Un approvisionnement SANS ligne est compté, et ne déplace aucun montant')]
    public function approvisionnementSansLigne(): void
    {
        /*
            CE QUE L'INNER JOIN FAUSSAIT — LE COMPTEUR, PAS LE MONTANT.

            Le premier jet de ce test visait le total, et il ne pouvait pas tomber : un
            approvisionnement sans ligne contribue ZÉRO, donc son absence de la somme ne la déplace pas.
            Vérifié en réintroduisant la jointure — le test restait vert. Ce qu'elle faussait vraiment,
            c'est `COUNT(DISTINCT a.id)` : l'approvisionnement existait et n'était pas compté.

            On en fabrique un directement : le contrat d'API l'interdit ('NotNull' + 'Count(min: 1)' sur
            details), mais une reprise de données, un import ou une commande ne passent pas par lui.
        */
        $avecLignes = $this->creer([['piece' => $this->pneu->getId(), 'quantite' => 2, 'prixunitaire' => 50000]]);

        $orphelin = (new Approvisionnement())
            ->setFournisseur($this->relire(Fournisseur::class, (int) $this->fournisseur->getId()))
            ->setDateappro(new \DateTimeImmutable())
            ->setIdentreprise($this->reseau->identreprise());
        $this->em->persist($orphelin);
        $this->em->flush();
        $this->em->clear();

        $achats = static::getContainer()->get(ApprovisionnementRepository::class)->achatsParFournisseur(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
            $this->reseau->identreprise()
        );
        self::assertCount(1, $achats, 'un seul fournisseur dans ce scénario');

        self::assertSame(2, (int) $achats[0]['nbappros'], 'LES DEUX approvisionnements sont comptés');
        self::assertSame(100000, (int) $achats[0]['montant'], 'et le montant reste celui des lignes réelles');
        self::assertSame(100000, $this->coutEntreprise(), 'le total ne bouge pas');
        self::assertSame(100000, $this->entete($avecLignes), 'celui qui a des lignes est intact');
    }

    #[Test]
    #[TestDox('La série par jour somme au total : les deux lisent la même colonne')]
    public function laSerieSommeAuTotal(): void
    {
        /*
            Les deux surfaces lisaient auparavant la même jointure, elles lisent maintenant la même
            colonne. La sentinelle vaut pour la suite : le jour où l'une des deux est réécrite, un écart
            entre la courbe et le total affiché juste au-dessus doit faire tomber un test, pas passer
            pour un arrondi.
        */
        $this->creer([['piece' => $this->filtre->getId(), 'quantite' => 7, 'prixunitaire' => 10000]]);
        $this->creer([['piece' => $this->pneu->getId(), 'quantite' => 1, 'prixunitaire' => 50000]]);

        $repo = static::getContainer()->get(ApprovisionnementRepository::class);
        $serie = $repo->coutParJour(
            new \DateTimeImmutable('2000-01-01'),
            new \DateTimeImmutable('2100-01-01'),
            $this->reseau->identreprise()
        );

        $sommeSerie = 0;
        foreach ($serie as $point) {
            $sommeSerie += (int) $point['montant'];
        }

        self::assertSame(120000, $this->coutEntreprise());
        self::assertSame($this->coutEntreprise(), $sommeSerie, 'la courbe tombe sur le total');
    }
}

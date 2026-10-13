<?php

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LE RÉSULTAT PAR LIGNE : ce qu'une ligne rapporte, moins ce que ses départs coûtent.
 *
 * Le résultat existait déjà par GARE ('DepenseGareService') et par DÉPART
 * ('VoyageResultatService') ; par LIGNE il manquait, alors que c'est l'échelle à laquelle on décide
 * d'ajouter un car ou d'arrêter une desserte.
 *
 * TROIS CHOSES QUE CE TEST TIENT, et que rien d'autre ne verrait :
 *
 *  1. LES DEUX MEMBRES PORTENT SUR LA MÊME PÉRIODE. La recette d'une ligne est bornée sur
 *     'datedepartprevue' des voyages ; les charges DOIVENT l'être aussi, et non sur la date de
 *     saisie de la dépense. Le carburant d'un départ du 30 septembre peut être saisi le 2 octobre :
 *     le compter en octobre laisserait la recette de ce départ seule de son côté.
 *  2. SEULES LES CHARGES RATTACHÉES À UN DÉPART comptent. Une dépense de structure — loyer,
 *     salaires — n'a aucun lien avec une ligne ; l'y faire entrer inventerait une clé de répartition.
 *  3. SANS LE DROIT DE LIRE LES CHARGES, le résultat vaut 'null' et non la recette. Un zéro
 *     afficherait un bénéfice intégral à quelqu'un qui n'a simplement pas le droit de voir les coûts.
 */
final class ResultatLigneTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    private Voyage $voyage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 10000);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 30),
            depart: new DateTimeImmutable('+2 hours')
        );
        $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo', prix: 10000);
    }

    #[Test]
    #[TestDox('Le résultat retranche les charges rattachées aux départs de la ligne')]
    public function leResultatRetrancheLesCharges(): void
    {
        $this->depensePourVoyage($this->voyage, 4000);

        $ligne = $this->premiereLigne();

        self::assertSame(10000.0, (float) $ligne['recette']);
        self::assertSame(4000.0, (float) $ligne['depenses']);
        self::assertSame(
            6000.0,
            (float) $ligne['resultat'],
            'recette moins les charges des départs : 10 000 − 4 000'
        );
    }

    #[Test]
    #[TestDox("Une charge hors départ n'entre pas dans le résultat d'une ligne")]
    public function uneChargeHorsDepartNEntrePas(): void
    {
        $this->depensePourVoyage($this->voyage, 4000);
        // Loyer, salaires : rattachés à aucun voyage, donc à aucune ligne.
        $this->scenario->depense($this->reseau->entreprise, montant: 900000);

        $ligne = $this->premiereLigne();

        self::assertSame(
            4000.0,
            (float) $ligne['depenses'],
            "une dépense sans voyage n'appartient à aucune ligne : l'y répartir demanderait une clé "
            . "inventée, et un loyer de 900 000 écraserait le résultat de toutes les lignes"
        );
        self::assertSame(6000.0, (float) $ligne['resultat']);
    }

    #[Test]
    #[TestDox('La charge suit la date du DÉPART, pas celle de sa saisie')]
    public function laChargeSuitLaDateDuDepart(): void
    {
        $this->depensePourVoyage($this->voyage, 4000);

        /*
            LE PIÈGE, monté À L'ENVERS pour qu'il soit DISCRIMINANT : un départ HORS période, dont la
            charge est saisie MAINTENANT — donc dans la fenêtre. Si l'on bornait sur la date de
            saisie, ses 9 000 s'ajouteraient au résultat d'une période à laquelle ce départ
            n'appartient pas.

            !! LA PREMIÈRE VERSION DE CE TEST NE PROUVAIT RIEN : elle antidatait la saisie et
            vérifiait que la charge restait comptée. Mesuré — elle passait au vert AUSSI en bornant
            sur 'd.createdAt'. Un test de période ne discrimine que si l'une des deux dates tombe
            HORS de la fenêtre et l'autre dedans.
        */
        $departLointain = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 30),
            depart: new DateTimeImmutable('+60 days')
        );
        $this->depensePourVoyage($departLointain, 9000);

        $debut = (new DateTimeImmutable('-1 day'))->format('Y-m-d');
        $fin = (new DateTimeImmutable('+7 days'))->format('Y-m-d');

        self::assertSame(
            4000.0,
            (float) $this->premiereLigne($debut, $fin)['depenses'],
            "seule la charge du départ DANS la période compte. Borner sur la date de saisie y "
            . "ajouterait les 9 000 d'un départ qui n'a pas encore eu lieu, face à une recette qui, "
            . "elle, ne contient rien de ce départ"
        );
    }

    #[Test]
    #[TestDox("L'admin voit les charges : la branche « sans droit » est une précaution, pas un cas vécu")]
    public function lAdminVoitLesCharges(): void
    {
        $this->depensePourVoyage($this->voyage, 4000);

        /*
            CE TEST DIT CE QUI EST VRAI, et non ce que j'avais supposé. L'endpoint exige 'ROLE_ADMIN',
            et ce rôle emporte la permission de lire les dépenses : un acteur qui atteint cette page
            SANS le droit n'existe pas aujourd'hui — vérifié, un second admin voit bien les 4 000.

            La branche 'null' de 'LigneStatsProvider' reste donc une PRÉCAUTION pour le jour où la
            page s'ouvrirait à un autre rôle (un chef de gare, par exemple). Elle n'est pas couverte
            par un test parce qu'elle n'est pas atteignable : l'écrire en prétendant la couvrir
            aurait donné une fausse garantie.
        */
        $ligne = $this->premiereLigne();

        self::assertSame(4000.0, (float) $ligne['depenses']);
        self::assertNotNull($ligne['resultat'], "l'admin lit un résultat, pas un trou");
    }

    #[Test]
    #[TestDox("Un billet à la corbeille ne donne aucune recette à sa ligne")]
    public function unBilletALaCorbeilleNeComptePas(): void
    {
        /*
            DÉFAUT RÉEL, trouvé le 08/10/2026 en croisant les surfaces : 'findAllAvecStats' filtrait
            bien la corbeille DU VOYAGE ('v.deletedAt') et PAS celle du billet. Sur la base de dev,
            la ligne « Korhogo → Abidjan », qui n'avait jamais rien vendu, affichait 11 000 F de
            recette et 11 000 F de résultat pour un unique billet supprimé.

            CE QUI L'A RÉVÉLÉ, et que rien d'autre ne pouvait voir : la SOMME des recettes par ligne
            dépassait la recette totale de l'entreprise. Un sous-ensemble ne peut pas excéder le
            tout — et pris isolément, chaque écran restait parfaitement crédible.

            !! LA LIGNE EST MONTÉE SANS AUCUNE VENTE VALIDE, pour que le test soit DISCRIMINANT : un
            billet supprimé et rien d'autre. Si le filtre disparaît, la recette passe de 0 à 7 000 ;
            si le test portait sur une ligne qui vend par ailleurs, l'erreur se noierait dans le reste.
        */
        $ligneMuette = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 30),
            depart: new DateTimeImmutable('+3 hours')
        );
        $supprime = $this->scenario->billet(
            $this->reseau, $ligneMuette, 5, 'Abidjan', 'Korhogo', prix: 7000
        );
        $supprime->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();

        $this->requete('GET', '/api/stats/ligne/performance?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);
        $lignes = $this->reponseJson()['performances'];

        $recetteTotale = 0.0;
        foreach ($lignes as $ligne) {
            $recetteTotale += (float) $ligne['recette'];
        }

        self::assertSame(
            10000.0,
            $recetteTotale,
            "seule la vente VALIDE compte : 10 000. Sans le filtre de corbeille, les 7 000 du billet "
            . "supprimé s'ajoutent et la somme des lignes dépasse la recette de l'entreprise"
        );

        foreach ($lignes as $ligne) {
            self::assertNotSame(
                7000.0,
                (float) $ligne['recette'],
                "aucune ligne ne doit porter la recette d'un billet mis à la corbeille — la "
                . "corbeille dit que la pièce n'aurait jamais dû être saisie, sa recette non plus"
            );
        }
    }

    /** @return array<string, mixed> */
    private function premiereLigne(string $debut = '2000-01-01', string $fin = '2100-01-01'): array
    {
        $this->requete('GET', "/api/stats/ligne/performance?debut={$debut}&fin={$fin}", $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson()['performances'][0];
    }

    private function depensePourVoyage(Voyage $voyage, int $montant): object
    {
        return $this->scenario->depense($this->reseau->entreprise, montant: $montant, voyage: $voyage);
    }
}

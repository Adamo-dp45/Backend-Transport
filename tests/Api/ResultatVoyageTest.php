<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReservationStatus;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * DEUX PERSONNES QUI OUVRENT LE MÊME DÉPART DOIVENT LIRE LE MÊME CHIFFRE.
 *
 * C'est la règle que garde ce fichier, et elle était fausse. La fiche d'un voyage composait ses
 * totaux en additionnant les collections d'API (`/api/tickets?voyage.id=`…), que
 * `GareScopeExtension` filtre : le même départ affichait 38 000 FCFA au chef d'Adjamé et 30 000 au
 * chef de Korhogo, l'écart étant une réservation payée Adjamé → Bouaké dont aucune des deux gares
 * n'est Korhogo. Personne ne pouvait s'en apercevoir sans comparer deux écrans côte à côte.
 *
 * La recette d'un départ est une propriété DU DÉPART, pas de la gare qui la regarde. D'où
 * `/api/voyages/{id}/resultat`, servi hors périmètre de gare — la VISIBILITÉ du voyage restant, elle,
 * bornée par la règle de ligne.
 *
 * Le scénario reproduit exactement le cas mesuré : une réservation payée qui ne touche NI la gare de
 * montée NI la gare de descente du lecteur.
 */
final class ResultatVoyageTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $chefOrigine;

    private User $chefTerminus;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Abidjan(0) → Bouaké(1) → Korhogo(2) : Korhogo ne touche ni la montée ni la descente de la
        // réservation du test, c'est là que se cachait l'écart.
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        // Un car avec des sièges : 'billet()' réclame le siège n° 1 du car affecté.
        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            $this->scenario->car($this->reseau->entreprise, 20)
        );

        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $this->chefOrigine = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan'),
            roles: ['ROLE_ADMIN_GARE']
        );
        $this->chefTerminus = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Korhogo'),
            roles: ['ROLE_ADMIN_GARE']
        );
    }

    private function resultat(User $acteur): array
    {
        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/resultat', $acteur);
        $this->assertStatut(200);

        return $this->reponseJson();
    }

    #[Test]
    #[TestDox('Deux chefs de gare lisent la MÊME recette sur le même départ')]
    public function memeRecettePourTousLesLecteurs(): void
    {
        // Le billet : les deux gares le voient de toute façon (périmètre de LIGNE).
        $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo', prix: 15000);

        /*
            LA RÉSERVATION QUI CRÉAIT L'ÉCART : payée, Abidjan → Bouaké. `Reservation` est de périmètre
            MULTI-GARES (`['gare', 'garedescente']`), donc invisible au chef de Korhogo — qui n'est ni
            la montée ni la descente. C'est ce 8 000 qui manquait sur son écran.
        */
        $this->scenario->reservation(
            $this->reseau, $this->voyage, 'Abidjan', 'Bouaké',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            prix: 8000,
            etatpaiement: 'PAYE',
            datepaiement: new DateTimeImmutable()
        );

        $attendu = 23000; // 15 000 de billet + 8 000 de réservation payée

        foreach (['administrateur' => $this->admin, 'chef Abidjan' => $this->chefOrigine, 'chef Korhogo' => $this->chefTerminus] as $qui => $acteur) {
            $r = $this->resultat($acteur);
            self::assertSame($attendu, $r['recette'], "recette vue par le $qui");
            self::assertSame(15000, $r['billets'], "billets vus par le $qui");
            self::assertSame(8000, $r['reservations'], "réservations vues par le $qui");
        }

        /*
            ET LA RAISON D'ÊTRE DE CETTE ROUTE, vérifiée plutôt que racontée : la COLLECTION, elle,
            divergE bien. C'est ce qui interdit de « simplifier » un jour le provider en rappelant
            '/api/reservations?voyage.id=' — le bug reviendrait sans qu'aucun autre test ne bronche.
        */
        self::assertCount(1, $this->reservationsVues($this->chefOrigine), 'la gare de montée voit sa réservation');
        self::assertCount(0, $this->reservationsVues($this->chefTerminus), "le terminus ne la voit PAS : c'est l'angle mort");
    }

    /** @return list<array<string, mixed>> les réservations du voyage telles que la COLLECTION les sert */
    private function reservationsVues(User $acteur): array
    {
        $this->requete('GET', '/api/reservations?voyage.id=' . $this->voyage->getId(), $acteur);
        $this->assertStatut(200);

        return $this->reponseJson()['member'] ?? $this->reponseJson()['hydra:member'] ?? [];
    }

    #[Test]
    #[TestDox('Le REMPLISSAGE est le même pour tous, pic et tronçons compris')]
    public function memeRemplissagePourTousLesLecteurs(): void
    {
        /*
            L'occupation souffrait du même mal que la recette : la fiche la rebâtissait à partir des
            collections d'API, si bien qu'un chef de gare voyait un car moins plein qu'il ne l'est. Elle
            vient maintenant de 'CapaciteService' — qui compte des SIÈGES sur des intervalles, la règle
            que le README désigne comme la plus facile à faire régresser.
        */
        $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo');
        $this->scenario->reservation(
            $this->reseau, $this->voyage, 'Abidjan', 'Bouaké',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            etatpaiement: 'PAYE',
            datepaiement: new DateTimeImmutable()
        );

        foreach (['chef Abidjan' => $this->chefOrigine, 'chef Korhogo' => $this->chefTerminus] as $qui => $acteur) {
            $r = $this->resultat($acteur);
            // Le billet tient une place d'Abidjan à Korhogo, la réservation une d'Abidjan à Bouaké :
            // deux places tenues au tronçon le plus chargé, celui qui part d'Abidjan.
            self::assertSame(2, $r['picOccupation'], "pic vu par le $qui");
            self::assertSame(20, $r['capacite'], "capacité vue par le $qui");
            self::assertSame(18, $r['placesRestantes'], "places restantes vues par le $qui");
            self::assertNotEmpty($r['troncons'], "tronçons vus par le $qui");
            self::assertSame(2, $r['troncons'][0]['occupation'], "premier tronçon vu par le $qui");
        }
    }

    #[Test]
    #[TestDox("Le billet né d'une réservation n'est pas compté deux fois")]
    public function billetDeReservationNeRecomptePas(): void
    {
        /*
            La règle de `RecetteGareService` : la recette d'une réservation est reconnue à SON paiement.
            Le billet émis ensuite ne la recompte pas — sans quoi une place réservée rapporterait
            double. C'est le `reservation IS NULL` de `TicketRepository::recettePourVoyage`.
        */
        $reservation = $this->scenario->reservation(
            $this->reseau, $this->voyage, 'Abidjan', 'Korhogo',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            prix: 15000,
            etatpaiement: 'PAYE',
            datepaiement: new DateTimeImmutable()
        );
        $billet = $this->scenario->billet($this->reseau, $this->voyage, 1, 'Abidjan', 'Korhogo', prix: 15000);
        $billet->setReservation($reservation);
        $this->em->flush();

        $r = $this->resultat($this->admin);

        self::assertSame(0, $r['billets'], 'le billet issu du bon ne porte pas la recette');
        self::assertSame(15000, $r['reservations']);
        self::assertSame(15000, $r['recette'], 'une place réservée rapporte UNE fois');
    }

    #[Test]
    #[TestDox('Les dépenses du voyage sont les mêmes pour tous, quelle que soit la gare qui les a engagées')]
    public function memesDepensesPourTousLesLecteurs(): void
    {
        $this->scenario->autoriser($this->chefTerminus, ['Depense' => ['VOIR']]);
        $type = $this->scenario->typedepense($this->reseau->entreprise, 'Frais de route');

        // Charge imputée à ABIDJAN : le chef de Korhogo ne la voit dans AUCUNE liste de dépenses…
        $depense = $this->scenario->depense($this->reseau->entreprise, 25000, $this->reseau->gare('Abidjan'));
        $depense->setVoyage($this->voyage)->setTypedepense($type);
        $this->em->flush();

        // … et pourtant elle doit peser sur le résultat du départ qu'il consulte.
        foreach (['administrateur' => $this->admin, 'chef Korhogo' => $this->chefTerminus] as $qui => $acteur) {
            $r = $this->resultat($acteur);
            self::assertSame(25000, $r['depenses'], "dépenses vues par le $qui");
            self::assertSame(1, $r['nbDepenses'], "nombre de lignes vu par le $qui");
            self::assertCount(1, $r['lignesDepenses'], "détail vu par le $qui");
            // L'IMPUTATION ne bouge pas parce que la lecture s'ouvre : la charge reste celle d'Abidjan.
            self::assertSame(
                $this->reseau->gare('Abidjan')->getLibelle(),
                $r['lignesDepenses'][0]['gare'],
                "imputation vue par le $qui"
            );
        }
    }

    #[Test]
    #[TestDox("Sans la permission Dépense, les charges valent NULL et non zéro")]
    public function sansPermissionLesChargesSontInconnues(): void
    {
        $agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Voyage' => ['VOIR']]
        );

        $depense = $this->scenario->depense($this->reseau->entreprise, 25000, $this->reseau->gare('Abidjan'));
        $depense->setVoyage($this->voyage);
        $this->em->flush();

        $r = $this->resultat($agent);

        /*
            NULL et non 0 : « je n'ai pas le droit de savoir » ne s'écrit pas comme « il n'y en a pas ».
            Un zéro ferait lire à l'agent un résultat égal à la recette, c'est-à-dire un chiffre FAUX.
            L'écran masque le bloc sur ce null.
        */
        self::assertNull($r['depenses']);
        self::assertNull($r['resultat']);
        self::assertSame([], $r['lignesDepenses']);
    }

    #[Test]
    #[TestDox("Un départ d'une ligne qui ne dessert pas ma gare reste introuvable")]
    public function visibiliteDuVoyageToujoursBornee(): void
    {
        // Ouvrir le périmètre des MONTANTS ne doit pas ouvrir celui des VOYAGES : sans cette garde, la
        // route servirait la recette de n'importe quel départ de l'entreprise.
        $autreReseau = $this->scenario->reseau(['Daloa', 'Man'], entreprise: $this->reseau->entreprise);
        $autreVoyage = $this->scenario->voyage($autreReseau);

        $this->requete('GET', '/api/voyages/' . $autreVoyage->getId() . '/resultat', $this->chefTerminus);
        $this->assertStatut(404);
    }
}

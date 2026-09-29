<?php

namespace App\Tests\Domain;

use App\Domain\Service\RecetteGareService;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Ticket;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UNE LIGNE EN CORBEILLE NE COMPTE DANS AUCUN TOTAL D'ARGENT — ET LE MÊME MONTANT DISPARAÎT PARTOUT.
 *
 * Le défaut corrigé le 28/09/2026, mesuré sur les données réelles avant correction : un billet de
 * 15 000 mis à la corbeille laissait `recettesTotales` (entreprise) et `recetteParGare` (tableau de
 * bord) INCHANGÉES, tout en retirant 15 000 de `recettePourVoyage` (fiche du voyage) et un passager de
 * `findRecapDestinations` (bordereau du chauffeur). Deux chiffres pour la même chose, sur le même
 * écran, sans que rien ne dise lequel mentait.
 *
 * LA CAUSE ÉTAIT STRUCTURELLE, pas un oubli isolé : il n'existe AUCUN filtre Doctrine global dans ce
 * projet, donc `deletedAt` ne joue que là où il est écrit à la main — 48 agrégats l'ignoraient.
 *
 * CE FICHIER MONTE LE CAS QUE LES DONNÉES NE MONTRENT PAS : aucune fixture ne met quoi que ce soit à la
 * corbeille (mesuré : zéro ligne supprimée dans toute la base). Sans ces tests, le défaut se
 * réintroduirait à la première requête écrite sans le filtre, et resterait invisible.
 */
final class CorbeilleRecetteTest extends ApiTestCase
{
    private Reseau $reseau;

    private TicketRepository $tickets;

    private BagageRepository $bagages;

    private CourrierRepository $courriers;

    private RecetteGareService $recetteGare;

    private const PRIX_BILLET = 15000;

    private const MONTANT_BAGAGE = 2500;

    private const MONTANT_COURRIER = 4000;

    private const FRAIS_SUIVI = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo']);
        $this->tickets = static::getContainer()->get(TicketRepository::class);
        $this->bagages = static::getContainer()->get(BagageRepository::class);
        $this->courriers = static::getContainer()->get(CourrierRepository::class);
        $this->recetteGare = static::getContainer()->get(RecetteGareService::class);
    }

    private function debut(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2000-01-01');
    }

    private function fin(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2100-01-01');
    }

    /** Toutes les surfaces d'argent d'une gare, d'un coup. */
    private function surfaces(int $gareId): array
    {
        $ide = $this->reseau->identreprise();
        $parGare = $this->recetteGare->parGare($this->debut(), $this->fin(), $ide);

        return [
            'entreprise billets' => (int) $this->tickets->recettesTotales($this->debut(), $this->fin(), $ide),
            'entreprise bagages' => (int) $this->bagages->recettesTotales($this->debut(), $this->fin(), $ide),
            'entreprise courriers' => (int) $this->courriers->recettesTotales($this->debut(), $this->fin(), $ide),
            'gare billets' => (int) ($parGare[$gareId]['billetsGuichet'] ?? 0),
            'gare bagages' => (int) ($parGare[$gareId]['bagagesGuichet'] ?? 0),
            'gare courriers' => (int) ($parGare[$gareId]['courriers'] ?? 0),
            'gare total' => (int) ($parGare[$gareId]['recetteTotale'] ?? 0),
        ];
    }

    /** Met une ligne à la corbeille exactement comme le fait `SoftDeleteProcessor`. */
    private function corbeille(object $entite): void
    {
        $entite->setDeletedAt(new \DateTimeImmutable())->setIsEtatdelete(true);
        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    #[TestDox("Un billet en corbeille quitte la recette de l'entreprise ET celle de la gare")]
    public function billetEnCorbeille(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, $this->scenario->car($this->reseau->entreprise, 20));
        $billet = $this->scenario->billet($this->reseau, $voyage, 1, 'Abidjan', 'Korhogo', prix: self::PRIX_BILLET);
        $this->em->flush();

        $gareId = (int) $this->reseau->gare('Abidjan')->getId();
        $avant = $this->surfaces($gareId);
        self::assertSame(self::PRIX_BILLET, $avant['entreprise billets'], 'le billet compte avant');
        self::assertSame(self::PRIX_BILLET, $avant['gare billets'], 'et il compte pour sa gare');

        $this->corbeille($this->em->find(Ticket::class, $billet->getId()));
        $apres = $this->surfaces($gareId);

        /*
            LES DEUX SURFACES ENSEMBLE. Vérifier la seule recette d'entreprise laisserait passer le
            défaut d'origine à moitié : c'était bien l'écart ENTRE les surfaces qui rendait le chiffre
            incorrigeable, pas sa valeur absolue.
        */
        self::assertSame(0, $apres['entreprise billets'], 'la recette de l\'entreprise le perd');
        self::assertSame(0, $apres['gare billets'], 'la recette de la gare le perd aussi');
        self::assertSame(0, $apres['gare total'], 'et le total composite de la gare');
    }

    #[Test]
    #[TestDox('Un bagage et un courrier en corbeille quittent les mêmes totaux')]
    public function bagageEtCourrierEnCorbeille(): void
    {
        $voyage = $this->scenario->voyage($this->reseau, $this->scenario->car($this->reseau->entreprise, 20));
        $billet = $this->scenario->billet($this->reseau, $voyage, 1, 'Abidjan', 'Korhogo', prix: self::PRIX_BILLET);
        $bagage = $this->scenario->bagage($this->reseau, $billet, montant: self::MONTANT_BAGAGE);
        $courrier = $this->scenario->courrier(
            $this->reseau, 'Abidjan', 'Korhogo',
            montant: self::MONTANT_COURRIER,
            fraissuivi: self::FRAIS_SUIVI
        );
        $this->em->flush();

        $gareId = (int) $this->reseau->gare('Abidjan')->getId();
        $avant = $this->surfaces($gareId);
        self::assertSame(self::MONTANT_BAGAGE, $avant['gare bagages']);
        self::assertSame(self::MONTANT_COURRIER + self::FRAIS_SUIVI, $avant['gare courriers'], 'frais de suivi compris');

        $idBagage = $bagage->getId();
        $idCourrier = $courrier->getId();
        $this->corbeille($this->em->find(Bagage::class, $idBagage));
        $this->corbeille($this->em->find(Courrier::class, $idCourrier));

        $apres = $this->surfaces($gareId);
        self::assertSame(0, $apres['entreprise bagages'], 'bagage : entreprise');
        self::assertSame(0, $apres['gare bagages'], 'bagage : gare');
        self::assertSame(0, $apres['entreprise courriers'], 'courrier : entreprise');
        self::assertSame(0, $apres['gare courriers'], 'courrier : gare');

        // Le billet, lui, n'a pas bougé : la corbeille ne touche que ce qu'on y met.
        self::assertSame(self::PRIX_BILLET, $apres['gare billets'], 'le billet reste intact');
    }

    #[Test]
    #[TestDox("TOUTES les surfaces bougent du MÊME montant — aucune ne reste en arrière")]
    public function toutesLesSurfacesBougentEnsemble(): void
    {
        /*
            LA SENTINELLE CENTRALE. Le défaut d'origine n'était pas « un total faux » mais « des totaux
            qui ne bougent pas ensemble » : la fiche du voyage et le bordereau perdaient le billet, le
            tableau de bord le gardait. On exige donc le MÊME écart sur les quatre surfaces, y compris
            celles qui filtraient déjà — sinon un correctif partiel passerait.
        */
        $voyage = $this->scenario->voyage($this->reseau, $this->scenario->car($this->reseau->entreprise, 20));
        $billet = $this->scenario->billet($this->reseau, $voyage, 1, 'Abidjan', 'Korhogo', prix: self::PRIX_BILLET);
        $this->scenario->billet($this->reseau, $voyage, 2, 'Abidjan', 'Korhogo', prix: self::PRIX_BILLET);
        $this->em->flush();

        $ide = $this->reseau->identreprise();
        $gareId = (int) $this->reseau->gare('Abidjan')->getId();
        $voyageId = (int) $voyage->getId();

        $lire = function () use ($ide, $gareId, $voyageId): array {
            $parGare = $this->recetteGare->parGare($this->debut(), $this->fin(), $ide);
            $recap = $this->tickets->findRecapDestinations($voyageId, $gareId, $ide);
            $nb = 0;
            foreach ($recap as $l) {
                $nb += (int) $l['nbtickets'];
            }

            return [
                'entreprise' => (int) $this->tickets->recettesTotales($this->debut(), $this->fin(), $ide),
                'gare' => (int) ($parGare[$gareId]['billetsGuichet'] ?? 0),
                'voyage' => (int) $this->tickets->recettePourVoyage($voyageId, $ide)['montant'],
                'bordereau' => $nb,
            ];
        };

        $avant = $lire();
        $this->corbeille($this->em->find(Ticket::class, $billet->getId()));
        $apres = $lire();

        self::assertSame(-self::PRIX_BILLET, $apres['entreprise'] - $avant['entreprise'], 'entreprise');
        self::assertSame(-self::PRIX_BILLET, $apres['gare'] - $avant['gare'], 'gare');
        self::assertSame(-self::PRIX_BILLET, $apres['voyage'] - $avant['voyage'], 'fiche du voyage');
        self::assertSame(-1, $apres['bordereau'] - $avant['bordereau'], 'bordereau du chauffeur');

        // Et le second billet reste, sur les quatre surfaces : on n'a pas tout effacé.
        self::assertSame(self::PRIX_BILLET, $apres['entreprise'], 'il reste exactement l\'autre billet');
        self::assertSame(1, $apres['bordereau']);
    }

    #[Test]
    #[TestDox("Le tableau ANTI-FRAUDE, lui, continue de voir la ligne supprimée")]
    public function lAntiFraudeVoitLargeExpres(): void
    {
        /*
            L'EXCEPTION VOULUE, et elle mérite sa sentinelle : filtrer la corbeille dans les surfaces de
            CONTRÔLE offrirait à un agent le moyen d'effacer ses propres traces — annuler, puis
            supprimer, et l'annulation quitterait le tableau. `suppressionsParAgent` existe pour
            attraper exactement ce geste, il cible donc `deletedAt IS NOT NULL`.
        */
        $voyage = $this->scenario->voyage($this->reseau, $this->scenario->car($this->reseau->entreprise, 20));
        $billet = $this->scenario->billet($this->reseau, $voyage, 1, 'Abidjan', 'Korhogo', prix: self::PRIX_BILLET);
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $billet->setCreatedBy((int) $agent->getId());
        $this->em->flush();

        $frais = $this->em->find(Ticket::class, $billet->getId());
        $frais->setDeletedBy((int) $agent->getId());
        $this->corbeille($frais);

        $lignes = $this->tickets->suppressionsParAgent($this->debut(), $this->fin(), $this->reseau->identreprise());

        $trouve = false;
        foreach ($lignes as $l) {
            if ((int) $l['agentid'] === (int) $agent->getId()) {
                $trouve = true;
                self::assertSame(
                    self::PRIX_BILLET,
                    (int) $l['montant'],
                    'le montant retiré du livre est CHIFFRÉ pour le contrôle'
                );
            }
        }
        self::assertTrue($trouve, 'la suppression reste attribuée à son auteur');
    }
}

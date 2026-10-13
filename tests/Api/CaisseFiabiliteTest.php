<?php

namespace App\Tests\Api;

use App\Domain\Enum\SessioncaisseStatut;
use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LA FIABILITÉ DES CAISSES, agent par agent — le seul chiffre de CONTRÔLE du projet.
 *
 * Toutes les autres statistiques mesurent l'ACTIVITÉ ; celle-ci mesure la VÉRACITÉ. Elle agrège ce
 * que la clôture produit depuis le 30/09/2026 et que rien ne lisait à l'échelle d'une période.
 *
 * DEUX PIÈGES, et chacun fausserait le classement dans un sens différent :
 *
 *  1. L'ÉCART NET EFFACE L'IMPRÉCISION. Un agent qui alterne +3 000 et −3 000 a un net NUL et ne
 *     tombe pourtant jamais juste. Classer là-dessus mettrait en tête un agent précis dont les rares
 *     écarts vont dans le même sens, et laisserait tranquille celui qui se trompe tous les jours.
 *  2. L'ATTENDU NÉGATIF N'EST PAS UN ÉCART. Il signifie qu'il est sorti de la caisse plus d'argent
 *     qu'il n'y est entré — un remboursement réglé avec des espèces du coffre, que rien ne permet
 *     encore de saisir. L'écart y vaut la totalité du réapprovisionnement non enregistré : un agent
 *     irréprochable arriverait en tête des suspects. On l'écarte, et on le COMPTE pour le dire.
 */
final class CaisseFiabiliteTest extends ApiTestCase
{
    private Reseau $reseau;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké']);
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
    }

    #[Test]
    #[TestDox("On classe sur l'écart ABSOLU : un net nul ne veut pas dire une caisse juste")]
    public function leClassementIgnoreLaCompensation(): void
    {
        // Celui-ci se trompe deux fois, dans les deux sens : net nul, imprécision bien réelle.
        $oscillant = $this->agent('Oscillant');
        $this->cloturer($oscillant, theorique: 50000, ecart: 3000);
        $this->cloturer($oscillant, theorique: 50000, ecart: -3000);

        // Celui-là se trompe une fois, plus légèrement.
        $leger = $this->agent('Leger');
        $this->cloturer($leger, theorique: 50000, ecart: 500);

        $lignes = $this->stats()['parAgent'];

        self::assertSame(
            'Prénom Oscillant',
            $lignes[0]['agent'],
            "le classement porte sur l'écart ABSOLU cumulé : 6 000 contre 500. Trier sur le net "
            . "aurait mis l'oscillant à 0 et l'aurait placé DERNIER, alors qu'il n'est jamais juste"
        );
        self::assertSame(6000, $lignes[0]['ecartAbsolu']);
        self::assertSame(0, $lignes[0]['ecartNet'], 'son net est nul : les deux écarts se compensent');
        self::assertSame(0, $lignes[0]['tauxJuste'], 'aucune de ses deux clôtures ne tombe juste');
    }

    #[Test]
    #[TestDox("Une caisse à attendu négatif est écartée du calcul, et annoncée")]
    public function lAttenduNegatifEstEcarteEtAnnonce(): void
    {
        $agent = $this->agent('Irreprochable');
        $this->cloturer($agent, theorique: 40000, ecart: 0);

        /*
            LE PIÈGE : un remboursement payé avec des espèces venues du coffre rend le théorique
            NÉGATIF, et l'écart affiché vaut alors tout le réapprovisionnement non saisi. Compté, il
            placerait cet agent — dont la seule vraie clôture tombe PILE — en tête du classement.
        */
        $this->cloturer($agent, theorique: -30000, ecart: 40000);

        $reponse = $this->stats();
        $ligne = $reponse['parAgent'][0];

        self::assertSame(1, $ligne['clotures'], 'la clôture au théorique négatif ne compte pas');
        self::assertSame(0, $ligne['ecartAbsolu'], "et son faux écart de 40 000 n'est pas retenu");
        self::assertSame(100, $ligne['tauxJuste'], 'cet agent tombe juste à 100 %, et doit le rester');

        self::assertSame(
            1,
            $reponse['clotureesEcartees'],
            "la clôture retirée est COMPTÉE : un classement amputé en silence ne se conteste pas, et "
            . "le motif du retrait est une limite connue du module — pas une anomalie de l'agent"
        );
    }

    #[Test]
    #[TestDox("L'écart est rapporté au volume brassé, sinon les gares ne se comparent pas")]
    public function lEcartEstRapporteAuVolume(): void
    {
        $petiteGare = $this->agent('Petit');
        $this->cloturer($petiteGare, theorique: 100000, ecart: 1000);

        $grandeGare = $this->agent('Grand');
        $this->cloturer($grandeGare, theorique: 4000000, ecart: 1000);

        $parAgent = [];
        foreach ($this->stats()['parAgent'] as $l) {
            $parAgent[$l['agent']] = $l;
        }

        // Comparaison en FLOAT : JSON rend 10.0 comme 10, et 'assertSame' distinguerait les deux types.
        self::assertSame(10.0, (float) $parAgent['Prénom Petit']['tauxEcart']);
        self::assertSame(
            0.25,
            (float) $parAgent['Prénom Grand']['tauxEcart'],
            "à écart égal, celui qui a manipulé quarante fois plus d'argent est quarante fois plus "
            . "précis. Sans ce rapport, une grande gare paraît toujours la pire"
        );
    }

    #[Test]
    #[TestDox("Le classement est fermé à qui n'est pas admin")]
    public function reserveALAdmin(): void
    {
        $this->requete('GET', '/api/stats/caisses', $this->agent('Simple'));
        $this->assertStatut(403);
    }

    private function agent(string $nom): User
    {
        $user = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $user->setNom($nom)->setPrenom('Prénom');
        $this->em->flush();

        return $user;
    }

    /**
     * Une caisse CLÔTURÉE posée directement en base : ce qui est éprouvé ici est l'AGRÉGATION, pas
     * le parcours de clôture — il a ses propres tests ('ClotureCaisseTest').
     */
    private function cloturer(User $agent, int $theorique, int $ecart): void
    {
        $session = (new Sessioncaisse())
            ->setAgent($agent)
            ->setGare($agent->getGare())
            ->setDatedebut(new DateTimeImmutable('-2 hours'))
            ->setDatefin(new DateTimeImmutable('-1 hour'))
            ->setFondsouverture(0)
            ->setMontanttheorique($theorique)
            ->setMontantcompte($theorique + $ecart)
            ->setEcart($ecart)
            ->setStatut(SessioncaisseStatut::CLOTUREE->value)
            ->setAgentsessionouverte(null);
        $session->setIdentreprise((int) $this->reseau->identreprise());

        if ($ecart !== 0) {
            $session->setMotifecart('Motif de test');
        }

        $this->em->persist($session);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $this->requete('GET', '/api/stats/caisses?debut=2000-01-01&fin=2100-01-01', $this->admin);
        $this->assertStatut(200);

        return $this->reponseJson();
    }
}

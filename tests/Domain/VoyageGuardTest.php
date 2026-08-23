<?php

namespace App\Tests\Domain;

use App\Entity\Voyage;
use App\Security\VoyageGuard;
use App\Tests\Support\IntegrationTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * La répartition des droits le long du trajet : « l'ORIGINE prépare · l'INTERMÉDIAIRE réceptionne ·
 * le TERMINUS clôture ».
 *
 * La distinction que ces tests protègent est celle entre PLANIFICATION (qui possède le départ) et
 * EXPLOITATION (qui peut intervenir sur le car en route) : une gare intermédiaire ne prépare pas un
 * voyage, mais elle doit pouvoir changer le véhicule si le car tombe en panne chez elle. Les
 * confondre reviendrait soit à bloquer l'exploitation en cas d'incident, soit à laisser n'importe
 * quelle gare replanifier le départ d'une autre.
 */
final class VoyageGuardTest extends IntegrationTestCase
{
    private VoyageGuard $guard;

    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guard = $this->service(VoyageGuard::class);
        $this->reseau = $this->scenario->reseau(['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo']);
    }

    // ------------------------------------------------------------------- PLANIFICATION ---------

    #[Test]
    #[TestDox("Seule la gare d'origine prépare le voyage ; l'intermédiaire et le terminus sont refusés")]
    public function planificationReserveeALOrigine(): void
    {
        $voyage = $this->voyage();

        // L'origine passe sans exception : c'est la garde qui ne doit rien lever.
        $this->guard->assertPeutPlanifier($this->agent('Abidjan'), $voyage);

        foreach (['Yamoussoukro', 'Korhogo'] as $gare) {
            try {
                $this->guard->assertPeutPlanifier($this->agent($gare), $voyage);
                self::fail(sprintf('La gare %s ne devrait pas pouvoir préparer ce voyage.', $gare));
            } catch (BadRequestHttpException $e) {
                self::assertStringContainsString('Seule la gare d\'origine', $e->getMessage());
            }
        }
    }

    #[Test]
    #[TestDox("L'admin et l'utilisateur central sans gare planifient n'importe quel voyage")]
    public function planificationLibrePourAdminEtCentral(): void
    {
        $voyage = $this->voyage();

        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);
        $central = $this->scenario->utilisateur($this->reseau->entreprise);

        $this->guard->assertPeutPlanifier($admin, $voyage);
        $this->guard->assertPeutPlanifier($central, $voyage);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[TestDox("Sur un départ partiel, c'est la gare de provenance réelle qui planifie, pas l'origine de la ligne")]
    public function planificationSuitLaProvenanceEffective(): void
    {
        $voyage = $this->voyage(provenance: 'Bouaké');

        // Bouaké a lancé ce départ : c'est elle qui le possède.
        $this->guard->assertPeutPlanifier($this->agent('Bouaké'), $voyage);

        $this->expectException(BadRequestHttpException::class);
        // Abidjan est pourtant l'origine de la LIGNE, mais elle n'a pas lancé CE voyage.
        $this->guard->assertPeutPlanifier($this->agent('Abidjan'), $voyage);
    }

    // -------------------------------------------------------------------- EXPLOITATION ---------

    #[Test]
    #[TestDox("Une gare intermédiaire peut intervenir sur le car en route, même sans pouvoir planifier")]
    public function exploitationOuverteAuxIntermediaires(): void
    {
        $voyage = $this->voyage();

        // Le cas de la panne : le car est immobilisé à Bouaké, la gare doit pouvoir le remplacer.
        $this->guard->assertPeutGerer($this->agent('Bouaké'), $voyage);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    #[TestDox('Le terminus ne prépare rien : il ne fait que clôturer')]
    public function exploitationRefuseeAuTerminus(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('ne fait que le clôturer');

        $this->guard->assertPeutGerer($this->agent('Korhogo'), $this->voyage());
    }

    #[Test]
    #[TestDox("Une gare située avant la provenance d'un départ partiel n'intervient pas dessus")]
    public function exploitationRefuseeEnAmontDeLaProvenance(): void
    {
        $voyage = $this->voyage(provenance: 'Bouaké');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('située avant la provenance');
        // Le car ne passe jamais par Abidjan sur ce départ.
        $this->guard->assertPeutGerer($this->agent('Abidjan'), $voyage);
    }

    // ------------------------------------------------------------------ CRÉATION / DÉPART ------

    #[Test]
    #[TestDox("Un agent lance le départ depuis SA gare ; une gare intermédiaire produit un départ partiel")]
    public function creationDepuisSaGare(): void
    {
        $normal = $this->guard->assertPeutCreerDepart($this->agent('Abidjan'), $this->reseau->ligne);
        $partiel = $this->guard->assertPeutCreerDepart($this->agent('Bouaké'), $this->reseau->ligne);

        self::assertSame($this->reseau->gare('Abidjan')->getId(), $normal->getId(), 'départ normal depuis l\'origine');
        self::assertSame($this->reseau->gare('Bouaké')->getId(), $partiel->getId(), 'la provenance devient Bouaké');
    }

    #[Test]
    #[TestDox("Un admin part toujours de l'origine de la ligne")]
    public function creationParAdminPartDeLOrigine(): void
    {
        $admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        self::assertSame(
            $this->reseau->gare('Abidjan')->getId(),
            $this->guard->assertPeutCreerDepart($admin, $this->reseau->ligne)->getId()
        );
    }

    #[Test]
    #[TestDox('Le terminus ne lance pas de départ')]
    public function creationRefuseeAuTerminus(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('ne lance pas de départ');

        $this->guard->assertPeutCreerDepart($this->agent('Korhogo'), $this->reseau->ligne);
    }

    #[Test]
    #[TestDox("Une gare non desservie par la ligne ne peut pas y lancer de départ")]
    public function creationRefuseeHorsLigne(): void
    {
        // Gare de la même compagnie, mais absente des arrêts de la ligne.
        $autre = $this->scenario->reseau(['Daloa', 'Man'], entreprise: $this->reseau->entreprise);
        $agentDaloa = $this->scenario->utilisateur($this->reseau->entreprise, gare: $autre->gare('Daloa'));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('n\'est pas desservie par cette ligne');

        $this->guard->assertPeutCreerDepart($agentDaloa, $this->reseau->ligne);
    }

    // ------------------------------------------------------------ CLÔTURE ET RÉCEPTION ---------

    #[Test]
    #[TestDox('Seul le terminus clôture le voyage')]
    public function clotureReserveeAuTerminus(): void
    {
        $voyage = $this->voyage();

        $this->guard->assertPeutCloturer($this->agent('Korhogo'), $voyage);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Seule la gare de destination');
        $this->guard->assertPeutCloturer($this->agent('Bouaké'), $voyage);
    }

    #[Test]
    #[TestDox('Seule une gare intermédiaire réceptionne : ni la provenance, ni le terminus')]
    public function receptionReserveeAuxIntermediaires(): void
    {
        $voyage = $this->voyage();

        $this->guard->assertPeutReceptionner($this->agent('Bouaké'), $voyage);

        foreach ([
            'Abidjan' => 'ne réceptionne pas un voyage : elle le lance',
            'Korhogo' => 'ne réceptionne pas le voyage : elle le clôture',
        ] as $gare => $message) {
            try {
                $this->guard->assertPeutReceptionner($this->agent($gare), $voyage);
                self::fail(sprintf('La gare %s ne devrait pas pouvoir réceptionner.', $gare));
            } catch (BadRequestHttpException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    #[Test]
    #[TestDox('Un voyage clôturé ne se réceptionne plus')]
    public function receptionRefuseeSurVoyageCloture(): void
    {
        $voyage = $this->voyage();
        $voyage->setDatearriveereelle(new DateTimeImmutable('-1 hour'));
        $this->em->flush();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('est clôturé');

        $this->guard->assertPeutReceptionner($this->agent('Bouaké'), $voyage);
    }

    // ------------------------------------------------------------- POSITION DU CAR -------------

    #[Test]
    #[TestDox("Tant que le voyage n'est pas parti, aucune gare n'est atteinte ni dépassée")]
    public function avantLeDepartRienNEstAtteint(): void
    {
        $voyage = $this->voyage();

        self::assertFalse($this->guard->monteeAtteinte($voyage, $this->reseau->gare('Abidjan')));
        self::assertFalse(
            $this->guard->monteeDepassee($voyage, $this->reseau->gare('Abidjan')),
            'un billet reste librement modifiable avant le départ'
        );
    }

    #[Test]
    #[TestDox("Être À la gare de montée n'est pas trop tard pour vendre, mais l'est pour se désister")]
    public function carArriveALaGareDeMontee(): void
    {
        // Le car est parti d'Abidjan et se trouve maintenant à Bouaké.
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-3 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $bouake = $this->reseau->gare('Bouaké');

        self::assertTrue(
            $this->guard->monteeAtteinte($voyage, $bouake),
            'service en cours : plus de désistement ni de modification du billet'
        );
        self::assertFalse(
            $this->guard->monteeDepassee($voyage, $bouake),
            "le car est à quai, c'est le moment où l'on embarque : la vente reste ouverte"
        );
    }

    #[Test]
    #[TestDox('Une fois le car reparti, la gare de montée est dépassée : plus de vente possible')]
    public function carRepartiDeLaGareDeMontee(): void
    {
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-5 hours'))
            ->setGarecourante($this->reseau->gare('Korhogo'));
        $this->em->flush();

        self::assertTrue($this->guard->monteeDepassee($voyage, $this->reseau->gare('Bouaké')));
    }

    #[Test]
    #[TestDox("La gare d'origine est dépassée dès que le car en est parti")]
    public function origineDepasseeDesLeDepart(): void
    {
        $voyage = $this->voyage();
        $voyage->setDatedepartreelle(new DateTimeImmutable('-10 minutes'));
        $this->em->flush();

        self::assertTrue(
            $this->guard->monteeDepassee($voyage, $this->reseau->gare('Abidjan')),
            "on ne vend pas une place au départ d'une gare que le car a quittée"
        );
        // Les gares en aval, elles, restent vendables : le car va encore les chercher.
        self::assertFalse($this->guard->monteeDepassee($voyage, $this->reseau->gare('Bouaké')));
    }

    #[Test]
    #[TestDox("Une gare INTERMÉDIAIRE que le car a quittée est dépassée, même si la position n'a pas bougé")]
    public function departDUneGareIntermediaire(): void
    {
        /*
            Le cas qui échappait à la règle : 'garecourante' n'avance qu'à la RÉCEPTION, donc à
            l'ARRIVÉE. Une fois le car reparti de Bouaké, la position reste « Bouaké » et les tests
            d'ordre répondaient faux — la gare continuait de vendre et de désister sur un car qu'elle
            venait de voir partir. Seul le départ consigné dans 'Passage' le dit.
        */
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-4 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $bouake = $this->reseau->gare('Bouaké');
        self::assertFalse($this->guard->monteeDepassee($voyage, $bouake), 'à quai : la vente reste ouverte');

        $this->scenario->passage($this->reseau, $voyage, 'Bouaké',
            arrivee: new DateTimeImmutable('-1 hour'),
            depart: new DateTimeImmutable('-30 minutes')
        );
        $this->em->refresh($voyage);

        self::assertTrue(
            $this->guard->monteeDepassee($voyage, $bouake),
            'le car est reparti : plus de vente, plus de modification, plus de désistement ici'
        );
    }

    #[Test]
    #[TestDox("Le départ d'une gare intermédiaire ne ferme pas les gares situées en aval")]
    public function departIntermediaireNeFermePasLAval(): void
    {
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-4 hours'))
            ->setGarecourante($this->reseau->gare('Yamoussoukro'));
        $this->em->flush();

        $this->scenario->passage($this->reseau, $voyage, 'Yamoussoukro',
            arrivee: new DateTimeImmutable('-2 hours'),
            depart: new DateTimeImmutable('-90 minutes')
        );
        $this->em->refresh($voyage);

        self::assertTrue($this->guard->monteeDepassee($voyage, $this->reseau->gare('Yamoussoukro')));
        self::assertFalse(
            $this->guard->monteeDepassee($voyage, $this->reseau->gare('Bouaké')),
            'le car roule vers Bouaké : on y vend encore'
        );
    }

    #[Test]
    #[TestDox("Les gares en aval de la position du car restent ouvertes à la vente")]
    public function garesEnAvalRestentOuvertes(): void
    {
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-3 hours'))
            ->setGarecourante($this->reseau->gare('Yamoussoukro'));
        $this->em->flush();

        self::assertFalse($this->guard->monteeAtteinte($voyage, $this->reseau->gare('Bouaké')));
        self::assertFalse($this->guard->monteeDepassee($voyage, $this->reseau->gare('Bouaké')));
    }

    // ------------------------------------------------------- BORNE DU COMMERCIAL EMBARQUÉ ------

    #[Test]
    #[TestDox("Le commercial garde la main sur le tronçon en cours, là où la gare l'a déjà perdue")]
    public function borneDuCommercialSurvitAuDepart(): void
    {
        /*
            L'ÉCART VOULU entre les deux bornes. Le commercial voyage avec le car et encaisse APRÈS
            le départ (passagers montés sans avoir payé) : juger ses billets avec 'monteeDepassee',
            la borne de la gare, lui masquait « Modifier » sur ce qu'il venait d'émettre en route.
        */
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-4 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        $bouake = $this->reseau->gare('Bouaké');
        $this->scenario->passage($this->reseau, $voyage, 'Bouaké',
            arrivee: new DateTimeImmutable('-1 hour'),
            depart: new DateTimeImmutable('-30 minutes')
        );
        $this->em->refresh($voyage);

        self::assertTrue(
            $this->guard->monteeDepassee($voyage, $bouake),
            'la gare de Bouaké a vu le car partir : elle ne corrige plus'
        );
        self::assertTrue(
            $this->guard->surLaGareDeMontee($voyage, $bouake),
            'le commercial est à bord et le car roule encore vers l\'escale suivante : il se relit'
        );
    }

    #[Test]
    #[TestDox("La correction du commercial se ferme à la réception suivante")]
    public function borneDuCommercialFermeALEscaleSuivante(): void
    {
        $voyage = $this->voyage();
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-4 hours'))
            ->setGarecourante($this->reseau->gare('Korhogo')) // le car a été réceptionné plus loin
            ->setDatearriveereelle(null);
        $this->em->flush();

        self::assertFalse(
            $this->guard->surLaGareDeMontee($voyage, $this->reseau->gare('Bouaké')),
            'la position a avancé : les billets vendus à Bouaké ne sont plus corrigibles'
        );
        self::assertTrue($this->guard->surLaGareDeMontee($voyage, $this->reseau->gare('Korhogo')));
    }

    #[Test]
    #[TestDox('Avant le départ, le commercial corrige les billets de la gare de départ')]
    public function borneDuCommercialAvantLeDepart(): void
    {
        $voyage = $this->voyage();

        self::assertTrue($this->guard->surLaGareDeMontee($voyage, $this->reseau->gare('Abidjan')));
        self::assertFalse($this->guard->surLaGareDeMontee($voyage, $this->reseau->gare('Bouaké')));
        self::assertFalse($this->guard->surLaGareDeMontee($voyage, null), 'sans gare de montée : rien à comparer');
        self::assertFalse($this->guard->surLaGareDeMontee(null, $this->reseau->gare('Abidjan')));
    }

    // --------------------------------------------------------------------------------------------

    private function voyage(?string $provenance = null): Voyage
    {
        return $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10),
            provenance: $provenance
        );
    }

    /** Un agent simple rattaché à la gare nommée. */
    private function agent(string $gare): \App\Entity\User
    {
        return $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare($gare));
    }
}

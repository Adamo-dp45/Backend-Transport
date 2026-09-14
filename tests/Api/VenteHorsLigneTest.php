<?php

namespace App\Tests\Api;

use App\Domain\Service\ActiviteLogger;
use App\Entity\Activite;
use App\Entity\Bagage;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * La remontée des ventes encaissées HORS LIGNE par le vendeur à bord
 * (`POST /api/voyages/{id}/me/sync`).
 *
 * Ce que ces tests protègent tient en une idée : **on ne défait pas une vente déjà encaissée**. Le
 * passager est assis, l'argent est dans la sacoche. Refuser un billet à la synchronisation ne
 * l'annulerait pas — cela le ferait seulement disparaître du système, avec sa recette.
 *
 * D'où deux comportements qui surprennent si on les lit comme une vente ordinaire : un siège déjà
 * occupé n'est PAS un refus (l'éviction arbitre ensuite), et un rejeu ne duplique rien (la référence
 * de l'appareil fait clé). Ce qui reste refusé, ce sont les incohérences qu'aucun rejeu ne réparera.
 */
final class VenteHorsLigneTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private User $commercial;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
        $this->scenario->tarif($this->reseau, 'Bouaké', 'Korhogo', 8000);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarifbagage($this->reseau->entreprise, 0, 20, 1000);
        $this->scenario->tarifbagage($this->reseau->entreprise, 21, null, 2500);

        // Rattaché à Abidjan : sa gare d'attache n'est PAS la position du car.
        $this->commercial = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan')),
            ['Ticket' => ['VOIR', 'CREER'], 'Bagage' => ['VOIR', 'CREER']]
        );

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10)
        );
        $this->voyage
            ->setCommercial($this->commercial)
            ->setDatedepartreelle(new DateTimeImmutable('-2 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();
    }

    #[Test]
    #[TestDox('Une vente hors ligne remonte avec le code imprimé sur le reçu du client')]
    public function laVenteRemonteAvecSonCode(): void
    {
        $this->synchroniser([$this->vente('ref-1', siege: 1, code: 'B1')]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(1, $reponse['acceptes']);
        self::assertSame('ACCEPTE', $reponse['resultats'][0]['statut']);

        $billet = $this->billetParReference('ref-1');
        self::assertNotNull($billet);
        self::assertSame(
            $this->voyage->getCodevoyage() . '-TCK-2026-B1',
            $billet->getCodeticket(),
            'le code a déjà été imprimé et encodé en QR : le serveur ne le régénère pas'
        );
        self::assertSame(8000, $billet->getPrix(), 'le prix vient de la grille, pas de l\'appareil');
        self::assertSame(
            $this->commercial->getId(),
            $billet->getCommercial()?->getId(),
            'la recette revient au vendeur à bord, pas à la gare de montée'
        );
    }

    #[Test]
    #[TestDox('Rejouer le même lot ne crée pas un second billet')]
    public function leRejeuNeDuplique(): void
    {
        $lot = [$this->vente('ref-1', siege: 1, code: 'B1')];

        $this->synchroniser($lot);
        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['acceptes']);

        // Le réseau a lâché pendant la réponse : le téléphone renvoie exactement le même lot.
        $this->synchroniser($lot);
        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(0, $reponse['acceptes']);
        self::assertSame(1, $reponse['dejaSynchronises']);

        self::assertCount(
            1,
            $this->em->getRepository(Ticket::class)->findBy(['voyage' => $this->voyage]),
            'un seul billet, quel que soit le nombre de rejeux'
        );
    }

    #[Test]
    #[TestDox('Un siège déjà vendu n\'est PAS un refus : le billet passe, l\'éviction arbitrera')]
    public function leSiegeOccupeNEstPasUnRefus(): void
    {
        /*
            Pendant que le commercial roulait sans réseau, la gare de Bouaké a vendu le même siège sur
            le même tronçon. En vente EN LIGNE, la seconde vente serait refusée ('Ce siège est déjà
            occupé'). Ici elle ne peut pas l'être : le passager est déjà à bord.
        */
        $this->scenario->billet($this->reseau, $this->voyage, siege: 1, de: 'Bouaké', a: 'Korhogo');

        $this->synchroniser([$this->vente('ref-1', siege: 1, code: 'B1')]);

        $this->assertStatut(200);
        self::assertSame(
            1,
            $this->reponseJson()['acceptes'],
            'on ne défait pas une vente encaissée : la doctrine d\'éviction tranchera qui monte'
        );
        self::assertNotNull($this->billetParReference('ref-1'));
    }

    #[Test]
    #[TestDox('Un écart entre le montant encaissé et la grille est consigné, pas appliqué')]
    public function lEcartDeTarifEstConsigne(): void
    {
        // Un admin a baissé le tarif Bouaké → Korhogo pendant le trajet ; le client a payé l'ancien.
        $this->synchroniser([$this->vente('ref-1', siege: 1, code: 'B1', encaisse: 9500)]);

        $this->assertStatut(200);
        $billet = $this->billetParReference('ref-1');
        self::assertSame(8000, $billet?->getPrix(), 'la grille fait foi — jamais l\'appareil');
        self::assertSame(9500, $billet?->getMontantEncaisse(), 'ce qui a été perçu est conservé tel quel');

        $trace = $this->em->getRepository(Activite::class)
            ->findOneBy(['type' => ActiviteLogger::TICKET_ECART_TARIF, 'cibleid' => $billet?->getId()]);
        self::assertNotNull($trace, 'sans trace, l\'écart entre le reçu papier et le billet passerait inaperçu');
    }

    #[Test]
    #[TestDox('Un voyage clôturé ferme la synchronisation')]
    public function leVoyageClotureFermeLaSynchronisation(): void
    {
        $this->voyage->setDatearriveereelle(new DateTimeImmutable('-10 minutes'));
        $this->em->flush();

        $this->synchroniser([$this->vente('ref-1', siege: 1, code: 'B1')]);

        $this->assertStatut(409);
        self::assertNull($this->billetParReference('ref-1'), 'rien n\'est écrit : cela se règle à la gare');
    }

    #[Test]
    #[TestDox('Un agent qui n\'est pas le commercial du voyage ne synchronise rien')]
    public function seulLeCommercialDuVoyageSynchronise(): void
    {
        $agent = $this->scenario->autoriser(
            $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké')),
            ['Ticket' => ['VOIR', 'CREER']]
        );

        $this->requete('POST', '/api/voyages/' . $this->voyage->getId() . '/me/sync', $agent, [
            'operations' => [$this->vente('ref-1', siege: 1, code: 'B1')],
        ]);

        $this->assertStatut(403);
        self::assertNull($this->billetParReference('ref-1'));
    }

    #[Test]
    #[TestDox('Une opération incohérente est refusée SANS emporter le reste du lot')]
    public function unRefusNEmportePasLeLot(): void
    {
        /*
            Le point essentiel : un vendeur qui remonte quinze billets après une journée sans réseau
            ne doit pas tout perdre parce que l'un d'eux est bancal.
        */
        $bancal = $this->vente('ref-bancale', siege: 1, code: 'B1');
        $bancal['payload']['siege'] = 999999; // siège d'un autre car

        $this->synchroniser([
            $bancal,
            $this->vente('ref-2', siege: 2, code: 'B2'),
            $this->vente('ref-3', siege: 3, code: 'B3'),
        ]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(2, $reponse['acceptes']);
        self::assertSame(1, $reponse['refuses']);
        self::assertSame('REFUSE', $reponse['resultats'][0]['statut']);
        self::assertNotSame('', $reponse['resultats'][0]['motif'] ?? '', 'le motif remonte au téléphone');

        self::assertNull($this->billetParReference('ref-bancale'));
        self::assertNotNull($this->billetParReference('ref-2'));
        self::assertNotNull($this->billetParReference('ref-3'));
    }

    #[Test]
    #[TestDox('Un code de billet manquant est refusé : le reçu du client en porte un')]
    public function leCodeEstObligatoire(): void
    {
        $sansCode = $this->vente('ref-1', siege: 1, code: 'B1');
        unset($sansCode['payload']['codeticket']);

        $this->synchroniser([$sansCode]);

        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['refuses']);
        self::assertNull($this->billetParReference('ref-1'));
    }

    #[Test]
    #[TestDox("L'avance de position remonte avec l'heure du téléphone, et se rejoue sans erreur")]
    public function laPositionRemonteEtSeRejoue(): void
    {
        $arrivee = new DateTimeImmutable('-40 minutes');
        $lot = [[
            'reference' => 'ref-pos-1',
            'type' => 'POSITION',
            'instant' => $arrivee->format(DATE_ATOM),
            'payload' => ['gare' => $this->reseau->gare('Korhogo')->getId()],
        ]];

        $this->synchroniser($lot);
        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['acceptes']);

        $this->em->refresh($this->voyage);
        self::assertSame(
            $this->reseau->gare('Korhogo')->getId(),
            $this->voyage->getGarecourante()?->getId(),
            'le car a bien avancé'
        );

        /*
            L'heure est celle du TÉLÉPHONE, pas celle de la synchronisation : sinon le car
            paraîtrait immobile pendant des heures puis téléporté, et le recalage des durées de
            tronçon s'en trouverait faussé.
        */
        $passage = $this->em->getRepository(\App\Entity\Passage::class)
            ->findOneBy(['voyage' => $this->voyage, 'gare' => $this->reseau->gare('Korhogo')]);
        self::assertNotNull($passage);
        self::assertSame(
            $arrivee->format('Y-m-d H:i'),
            $passage->getArriveeReelle()?->format('Y-m-d H:i'),
            'l\'horodatage déclaré à bord fait foi'
        );

        // Rejeu : le car y est déjà. Ce n'est pas une erreur.
        $this->synchroniser($lot);
        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(0, $reponse['acceptes']);
        self::assertSame(1, $reponse['dejaSynchronises']);
    }

    #[Test]
    #[TestDox("L'instantané embarque de quoi vendre seul : arrêts, sièges, billets, grille, plafond")]
    public function lInstantaneEmbarqueDeQuoiVendreSeul(): void
    {
        $this->scenario->configRemise($this->reseau->entreprise, 20);
        $this->scenario->billet($this->reseau, $this->voyage, siege: 4, de: 'Abidjan', a: 'Korhogo');

        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/me/instantane', $this->commercial);

        $this->assertStatut(200);
        $instantane = $this->reponseJson();

        self::assertCount(3, $instantane['arrets'], 'la ligne entière, pour juger la cohérence du tronçon');
        self::assertSame('Abidjan', $instantane['arrets'][0]['libelle'], 'les arrêts sont ORDONNÉS');
        self::assertCount(10, $instantane['sieges'], 'le plan du car');
        self::assertArrayHasKey(
            'rangee',
            $instantane['sieges'][0],
            'la DISPOSITION accompagne chaque siège : sans elle le plan se dessine sur une seule ligne'
        );
        self::assertArrayHasKey('cote', $instantane['sieges'][0]);
        self::assertNotEmpty($instantane['billets'], 'les places déjà prises, pour le plan de sièges hors ligne');
        self::assertSame(20, $instantane['plafondRemisePourcentage']);

        // La grille : le prix est le seul calcul serveur qu'un téléphone peut refaire à l'identique.
        $couples = array_map(
            static fn (array $t): string => $t['departId'] . '>' . $t['arriveeId'],
            $instantane['tarifs']
        );
        self::assertContains(
            $this->reseau->gare('Bouaké')->getId() . '>' . $this->reseau->gare('Korhogo')->getId(),
            $couples
        );
    }

    #[Test]
    #[TestDox("Un agent étranger au voyage n'en obtient pas l'instantané")]
    public function lInstantaneEstReserveAuCommercialDuVoyage(): void
    {
        $agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Bouaké'));

        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/me/instantane', $agent);

        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox('Un bagage hors ligne retrouve son billet par le CODE imprimé, pas par un identifiant')]
    public function leBagageRetrouveSonBilletParLeCode(): void
    {
        /*
            LE CAS QUI JUSTIFIE TOUT LE MÉCANISME : le billet et son bagage remontent dans le MÊME
            lot. Au moment où le téléphone a enregistré le bagage, le billet n'avait strictement
            aucun identifiant serveur — il attendait deux lignes plus haut dans la file. Seul le code
            imprimé sur le reçu faisait le lien.
        */
        $this->synchroniser([
            $this->vente('ref-v', siege: 1, code: 'B1'),
            $this->bagage('ref-b', codeBillet: 'B1', poids: 25),
        ]);

        $this->assertStatut(200);
        self::assertSame(2, $this->reponseJson()['acceptes']);

        $bagage = $this->bagageParReference('ref-b');
        self::assertNotNull($bagage);
        self::assertSame(
            $this->billetParReference('ref-v')?->getId(),
            $bagage->getTicket()?->getId(),
            'le bagage est rattaché au billet vendu quelques secondes plus tôt, hors ligne'
        );
        self::assertSame(2500, $bagage->getMontant(), 'la grille de poids du SERVEUR décide, pas l\'appareil');
        self::assertSame(
            $this->commercial->getId(),
            $bagage->getCommercial()?->getId(),
            'la recette du bagage revient au vendeur à bord'
        );
    }

    #[Test]
    #[TestDox("Le code d'étiquette du bagage est repris tel quel : il est collé sur le bagage")]
    public function leCodeDEtiquetteEstRepris(): void
    {
        $this->synchroniser([
            $this->vente('ref-v', siege: 1, code: 'B1'),
            $this->bagage('ref-b', codeBillet: 'B1'),
        ]);

        $this->assertStatut(200);
        self::assertSame(
            $this->voyage->getCodevoyage() . '-BAG-2026-B1',
            $this->bagageParReference('ref-b')?->getCodebagage(),
            'régénérer le code rendrait illisible l\'étiquette déjà remise au client'
        );
    }

    #[Test]
    #[TestDox('Rejouer un lot ne crée pas un second bagage')]
    public function leRejeuNeDupliquePasLeBagage(): void
    {
        $lot = [
            $this->vente('ref-v', siege: 1, code: 'B1'),
            $this->bagage('ref-b', codeBillet: 'B1'),
        ];

        $this->synchroniser($lot);
        $this->assertStatut(200);

        $this->synchroniser($lot);
        $this->assertStatut(200);
        self::assertSame(2, $this->reponseJson()['dejaSynchronises']);

        self::assertCount(
            1,
            $this->em->getRepository(Bagage::class)->findBy(['voyage' => $this->voyage]),
            'un seul bagage, quel que soit le nombre de rejeux'
        );
    }

    #[Test]
    #[TestDox('Un écart entre le montant perçu à bord et la grille de poids est consigné')]
    public function lEcartDeTarifBagageEstConsigne(): void
    {
        // La grille embarquée facturait 2000 ; celle du serveur dit 2500 pour ce poids.
        $this->synchroniser([
            $this->vente('ref-v', siege: 1, code: 'B1'),
            $this->bagage('ref-b', codeBillet: 'B1', poids: 25, encaisse: 2000),
        ]);

        $this->assertStatut(200);
        $bagage = $this->bagageParReference('ref-b');
        self::assertSame(2500, $bagage?->getMontant(), 'la grille du serveur fait foi');
        self::assertSame(2000, $bagage?->getMontantEncaisse());
        self::assertFalse(
            $bagage?->isMontantforce(),
            'l\'agent n\'a rien forcé : il a appliqué la grille qu\'il avait à bord'
        );

        $trace = $this->em->getRepository(Activite::class)
            ->findOneBy(['type' => ActiviteLogger::BAGAGE_ECART_TARIF, 'cibleid' => $bagage?->getId()]);
        self::assertNotNull($trace, 'la gare doit pouvoir régulariser la différence');
    }

    #[Test]
    #[TestDox('Un bagage dont le billet a été refusé est refusé à son tour, en le disant')]
    public function leBagageSuitLeSortDeSonBillet(): void
    {
        $bancal = $this->vente('ref-v', siege: 1, code: 'B1');
        $bancal['payload']['siege'] = 999999; // siège d'un autre car : la vente sera refusée

        $this->synchroniser([$bancal, $this->bagage('ref-b', codeBillet: 'B1')]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(2, $reponse['refuses']);
        self::assertStringContainsString(
            'introuvable',
            $reponse['resultats'][1]['motif'] ?? '',
            'le motif doit renvoyer au vrai problème — la vente refusée — pas en inventer un second'
        );
        self::assertNull($this->bagageParReference('ref-b'));
    }

    #[Test]
    #[TestDox("Un bagage rattaché au billet d'un autre voyage est refusé")]
    public function leBagageNeChangePasDeVoyage(): void
    {
        // Un bagage voyage dans la soute du car où monte son propriétaire : le rattacher au départ
        // d'à côté n'aurait aucun sens physique.
        $autreVoyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 10)
        );
        $billetAilleurs = $this->scenario->billet($this->reseau, $autreVoyage, siege: 1, de: 'Abidjan', a: 'Korhogo');

        $operation = $this->bagage('ref-b', codeBillet: 'B1');
        $operation['payload']['codeticket'] = $billetAilleurs->getCodeticket();

        $this->synchroniser([$operation]);

        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['refuses']);
        self::assertNull($this->bagageParReference('ref-b'));
    }

    #[Test]
    #[TestDox('Le lot ne dispense pas des permissions : sans le droit de créer, rien ne passe')]
    public function leLotNeDispensePasDesPermissions(): void
    {
        /*
            Être le commercial DU VOYAGE est un bornage, pas un droit. Le pipeline API Platform
            impose 'is_granted' sur chaque opération ; ce lot en sort, et sans contrôle explicite un
            vendeur privé du droit de créer un billet l'obtiendrait en passant hors ligne.
        */
        $sansDroit = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $this->voyage->setCommercial($sansDroit);
        $this->em->flush();

        $this->requete('POST', '/api/voyages/' . $this->voyage->getId() . '/me/sync', $sansDroit, [
            'operations' => [$this->vente('ref-1', siege: 1, code: 'B1')],
        ]);

        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['refuses']);
        self::assertNull($this->billetParReference('ref-1'));
    }

    #[Test]
    #[TestDox("L'instantané embarque la grille de poids : sans elle, aucun bagage hors ligne")]
    public function lInstantaneEmbarqueLaGrilleDePoids(): void
    {
        $this->requete('GET', '/api/voyages/' . $this->voyage->getId() . '/me/instantane', $this->commercial);

        $this->assertStatut(200);
        $tranches = $this->reponseJson()['tarifsBagage'];

        self::assertCount(2, $tranches);
        self::assertSame(0, $tranches[0]['poidsmin'], 'les tranches sont ORDONNÉES : la première qui couvre gagne');
        self::assertNull($tranches[1]['poidsmax'], 'la dernière tranche est illimitée');
    }

    #[Test]
    #[TestDox("Le départ de gare remonte avec l'heure du téléphone, et se rejoue sans erreur")]
    public function leDepartRemonteEtSeRejoue(): void
    {
        /*
            Arrivée puis départ, dans le même lot et dans cet ordre : c'est le geste réel du vendeur à
            chaque arrêt. Les deux horodatages donnent le TEMPS D'ARRÊT, et c'est pour le mesurer que
            l'heure du téléphone fait foi — celle de la synchronisation donnerait un arrêt de
            plusieurs heures là où le car s'est arrêté dix minutes.
        */
        $arrivee = new DateTimeImmutable('-50 minutes');
        $depart = new DateTimeImmutable('-40 minutes');

        $this->synchroniser([
            [
                'reference' => 'ref-pos',
                'type' => 'POSITION',
                'instant' => $arrivee->format(DATE_ATOM),
                'payload' => ['gare' => $this->reseau->gare('Korhogo')->getId()],
            ],
        ]);
        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['acceptes']);

        // Korhogo est le terminus de ce réseau : on recule la position sur Bouaké pour un départ
        // d'une gare INTERMÉDIAIRE, seul cas où le geste a un sens.
        $this->em->refresh($this->voyage);
        $this->voyage->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();
        $this->scenario->passage($this->reseau, $this->voyage, 'Bouaké', arrivee: $arrivee);

        $lot = [[
            'reference' => 'ref-dep',
            'type' => 'DEPART',
            'instant' => $depart->format(DATE_ATOM),
            'payload' => [],
        ]];

        $this->synchroniser($lot);
        $this->assertStatut(200);
        self::assertSame(1, $this->reponseJson()['acceptes']);

        $passage = $this->em->getRepository(\App\Entity\Passage::class)
            ->findOneBy(['voyage' => $this->voyage, 'gare' => $this->reseau->gare('Bouaké')]);
        self::assertNotNull($passage);
        self::assertSame(
            $depart->format('Y-m-d H:i'),
            $passage->getDepartReelle()?->format('Y-m-d H:i'),
            'l\'horodatage déclaré à bord fait foi : c\'est lui qui donne le temps d\'arrêt'
        );

        // Rejeu : le départ est déjà horodaté. Ce n'est pas une erreur.
        $this->synchroniser($lot);
        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(0, $reponse['acceptes']);
        self::assertSame(1, $reponse['dejaSynchronises']);
    }

    #[Test]
    #[TestDox("Dans un même lot, le départ voit l'arrivée qui le précède")]
    public function leDepartVoitLArriveeDuMemeLot(): void
    {
        /*
            LE CAS RÉEL DU VENDEUR : il déclare l'arrivée à une gare, vend, puis déclare le départ —
            le tout sans réseau, et tout remonte dans le même lot.

            Le piège est invisible en test isolé : 'PassageService' persiste SANS flusher, si bien
            qu'une requête en base ne voit pas l'arrivée que l'opération précédente vient de poser.
            Le départ était alors refusé au motif que le car n'était jamais arrivé — là où le lot
            venait précisément de le déclarer.
        */
        $arrivee = new DateTimeImmutable('-50 minutes');
        $depart = new DateTimeImmutable('-40 minutes');

        // Le voyage part d'Abidjan et est à Bouaké ; on le ramène en amont pour que l'arrivée à
        // Bouaké soit encore à déclarer.
        $this->voyage->setGarecourante($this->reseau->gare('Abidjan'));
        $this->em->flush();

        $this->synchroniser([
            [
                'reference' => 'ref-pos',
                'type' => 'POSITION',
                'instant' => $arrivee->format(DATE_ATOM),
                'payload' => ['gare' => $this->reseau->gare('Bouaké')->getId()],
            ],
            [
                'reference' => 'ref-dep',
                'type' => 'DEPART',
                'instant' => $depart->format(DATE_ATOM),
                'payload' => [],
            ],
        ]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(
            2,
            $reponse['acceptes'],
            'le départ doit voir l\'arrivée posée deux lignes plus haut, pas la chercher en base'
        );

        $passage = $this->em->getRepository(\App\Entity\Passage::class)
            ->findOneBy(['voyage' => $this->voyage, 'gare' => $this->reseau->gare('Bouaké')]);
        self::assertNotNull($passage);
        self::assertSame($arrivee->format('Y-m-d H:i'), $passage->getArriveeReelle()?->format('Y-m-d H:i'));
        self::assertSame(
            $depart->format('Y-m-d H:i'),
            $passage->getDepartReelle()?->format('Y-m-d H:i'),
            'les deux horodatages du téléphone donnent le temps d\'arrêt en gare'
        );
    }

    #[Test]
    #[TestDox("Le car ne repart pas du TERMINUS : le départ y est refusé")]
    public function leDepartDuTerminusEstRefuse(): void
    {
        /*
            D'un terminus, le car ne repart pas — c'est la fin de la course. La règle vaut des deux
            côtés : le serveur la refuse ici, et l'application ne propose pas le geste (ni en ligne,
            où 'CommercialEspaceController' calcule 'peutRepartir', ni hors ligne, où la correction
            locale du voyage applique la même exclusion).
        */
        $arrivee = new DateTimeImmutable('-30 minutes');

        $this->synchroniser([
            [
                'reference' => 'ref-pos',
                'type' => 'POSITION',
                'instant' => $arrivee->format(DATE_ATOM),
                'payload' => ['gare' => $this->reseau->gare('Korhogo')->getId()],
            ],
            [
                'reference' => 'ref-dep',
                'type' => 'DEPART',
                'instant' => (new DateTimeImmutable('-20 minutes'))->format(DATE_ATOM),
                'payload' => [],
            ],
        ]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(1, $reponse['acceptes'], 'l\'arrivée au terminus, elle, est légitime');
        self::assertSame(1, $reponse['refuses']);
        self::assertStringContainsString('terminus', $reponse['resultats'][1]['motif'] ?? '');

        $passage = $this->em->getRepository(\App\Entity\Passage::class)
            ->findOneBy(['voyage' => $this->voyage, 'gare' => $this->reseau->gare('Korhogo')]);
        self::assertNull(
            $passage?->getDepartReelle(),
            'aucun départ n\'est horodaté au terminus'
        );
    }

    #[Test]
    #[TestDox("Un départ déclaré avant l'arrivée à la gare est refusé")]
    public function leDepartExigeUneArriveeEnregistree(): void
    {
        // Le car ne peut pas quitter une gare où il n'est pas arrivé. Aucun rejeu ne réparera ça :
        // c'est un refus, pas un conflit.
        $this->synchroniser([[
            'reference' => 'ref-dep',
            'type' => 'DEPART',
            'instant' => (new DateTimeImmutable('-10 minutes'))->format(DATE_ATOM),
            'payload' => [],
        ]]);

        $this->assertStatut(200);
        $reponse = $this->reponseJson();
        self::assertSame(1, $reponse['refuses']);
        self::assertStringContainsString('arrivée', $reponse['resultats'][0]['motif'] ?? '');
    }

    // --------------------------------------------------------------------------------------------

    /**
     * Une vente telle que le téléphone la met en file : le code est DÉJÀ définitif (il est imprimé),
     * la référence est l'identifiant d'idempotence de l'appareil.
     *
     * @return array<string, mixed>
     */
    private function vente(string $reference, int $siege, string $code, ?int $encaisse = null): array
    {
        return [
            'reference' => $reference,
            'type' => 'VENTE',
            'payload' => [
                'codeticket' => $this->voyage->getCodevoyage() . '-TCK-2026-' . $code,
                'gare' => $this->reseau->gare('Bouaké')->getId(),
                'garedescente' => $this->reseau->gare('Korhogo')->getId(),
                'siege' => $this->scenario->siege($this->voyage, $siege)->getId(),
                'nomclient' => 'Konan Aya',
                'contactclient' => '+225 07 00 00 00 0' . $siege,
                'montantEncaisse' => $encaisse,
            ],
        ];
    }

    /**
     * Un bagage tel que le téléphone le met en file : l'étiquette porte DÉJÀ son code, et le billet
     * est désigné par le sien — le seul identifiant qui existe quand la vente attend dans la même
     * file.
     *
     * @return array<string, mixed>
     */
    private function bagage(
        string $reference,
        string $codeBillet,
        int $poids = 25,
        ?int $encaisse = null,
        ?int $montantForce = null
    ): array {
        return [
            'reference' => $reference,
            'type' => 'BAGAGE',
            'payload' => [
                'codebagage' => $this->voyage->getCodevoyage() . '-BAG-2026-B1',
                'codeticket' => $this->voyage->getCodevoyage() . '-TCK-2026-' . $codeBillet,
                'nature' => 'Valise',
                'type' => 'LOURD',
                'poids' => $poids,
                'montant' => $montantForce,
                'montantEncaisse' => $encaisse,
            ],
        ];
    }

    /** @param list<array<string, mixed>> $operations */
    private function synchroniser(array $operations): void
    {
        $this->requete(
            'POST',
            '/api/voyages/' . $this->voyage->getId() . '/me/sync',
            $this->commercial,
            ['operations' => $operations]
        );
    }

    private function billetParReference(string $reference): ?Ticket
    {
        return $this->em->getRepository(Ticket::class)->findOneBy(['referenceOffline' => $reference]);
    }

    private function bagageParReference(string $reference): ?Bagage
    {
        return $this->em->getRepository(Bagage::class)->findOneBy(['referenceOffline' => $reference]);
    }
}

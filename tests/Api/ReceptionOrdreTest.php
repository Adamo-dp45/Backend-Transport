<?php

namespace App\Tests\Api;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Bagage;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Voyage;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * L'ORDRE DES RÉCEPTIONS, et le rattrapage d'un passage oublié.
 *
 * Une gare en aval qui réceptionne trop tôt ne se trompe pas seulement d'horodatage : la réception
 * AVANCE la position du car, et tout ce qui en dépend punit alors les gares survolées. Bouaké
 * réceptionnant avant que le car n'atteigne Yamoussoukro fermait d'un coup, pour Yamoussoukro, les
 * réservations vivantes (place rendue, paiement clos), la vente, les corrections, les désistements —
 * et le départ disparaissait de son sélecteur sans le moindre message. Sans retour en arrière
 * possible : `garecourante` n'est pas écrivable et n'avance que dans un sens.
 *
 * D'où la garde d'ordre. Et, parce qu'un oubli ne doit pas bloquer une ligne entière, le rattrapage
 * explicite par un administrateur — étroit, horodaté à l'heure réelle, et tracé.
 */
final class ReceptionOrdreTest extends ApiTestCase
{
    private Reseau $reseau;

    private Voyage $voyage;

    private int $voyageId;

    /** @var array<string, User> */
    private array $agents = [];

    private User $admin;

    private int $reservationYamId;

    private int $bagageYamId;

    protected function setUp(): void
    {
        parent::setUp();

        // Quatre arrêts : il faut DEUX intermédiaires pour qu'une réception puisse en sauter une.
        $this->reseau = $this->scenario->reseau(
            ['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo'],
            [null, 180, 120, 180]
        );
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Korhogo', 15000);
        $this->scenario->tarif($this->reseau, 'Abidjan', 'Yamoussoukro', 6000);

        $this->voyage = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 20),
            depart: new DateTimeImmutable('-30 minutes')
        );
        $this->voyageId = (int) $this->voyage->getId();

        foreach (['Abidjan', 'Yamoussoukro', 'Bouaké', 'Korhogo'] as $gare) {
            $this->agents[$gare] = $this->scenario->utilisateur(
                $this->reseau->entreprise,
                gare: $this->reseau->gare($gare),
                roles: ['ROLE_ADMIN_GARE']
            );
        }
        // Administrateur d'entreprise : sans gare, c'est lui qui rattrape.
        $this->admin = $this->scenario->utilisateur($this->reseau->entreprise, roles: ['ROLE_ADMIN']);

        // Ce que la réception prématurée de Bouaké détruisait chez Yamoussoukro.
        $this->reservationYamId = (int) $this->scenario->reservation(
            $this->reseau,
            $this->voyage,
            'Yamoussoukro',
            'Korhogo',
            statut: ReservationStatus::STATUT_CONFIRMEE,
            expiration: new DateTimeImmutable('+4 hours'),
        )->getId();

        $billet = $this->scenario->billet($this->reseau, $this->voyage, siege: 1, de: 'Abidjan', a: 'Yamoussoukro');
        $this->bagageYamId = (int) $this->scenario->bagage($this->reseau, $billet, statut: 'EMBARQUE')->getId();
    }

    #[Test]
    #[TestDox("Bouaké ne réceptionne pas tant que Yamoussoukro n'a pas été pointée")]
    public function receptionQuiSauteUnArretRefusee(): void
    {
        $this->demarrer();

        $this->receptionner('Bouaké');

        $this->assertStatut(400);
        self::assertStringContainsString('Yamoussoukro', $this->messageErreur(), 'le refus doit NOMMER la gare oubliée');
        self::assertStringContainsString('rattraper', $this->messageErreur(), 'et dire par où sortir');
    }

    #[Test]
    #[TestDox("Le refus laisse Yamoussoukro intacte : sa réservation tient toujours sa place")]
    public function leRefusNeCoutRienAuxGaresSurvolees(): void
    {
        /*
            C'EST TOUT L'ENJEU. Avant la garde, la tentative de Bouaké passait : la position avançait,
            et `cloturerMonteesDepassees` ramenait l'échéance de cette réservation à l'instant présent
            — place rendue, paiement clos, cliente à reloger. Alors que le car n'était pas encore
            arrivé chez elle.
        */
        $this->demarrer();
        $avant = $this->relire(Reservation::class, $this->reservationYamId)->getDateexpiration();

        $this->receptionner('Bouaké');
        $this->assertStatut(400);

        $voyage = $this->relire(Voyage::class, $this->voyageId);
        self::assertSame(
            $this->reseau->gare('Abidjan')->getId(),
            $voyage->getGarecourante()?->getId(),
            'la position ne bouge pas sur une réception refusée'
        );
        self::assertEquals(
            $avant,
            $this->relire(Reservation::class, $this->reservationYamId)->getDateexpiration(),
            'l\'échéance de la réservation amont est intacte'
        );
    }

    #[Test]
    #[TestDox('Dans l\'ordre, les réceptions passent')]
    public function receptionsDansLOrdre(): void
    {
        $this->demarrer();

        $this->receptionner('Yamoussoukro');
        $this->assertStatut(200);

        $this->receptionner('Bouaké');
        $this->assertStatut(200);

        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->relire(Voyage::class, $this->voyageId)->getGarecourante()?->getId()
        );
    }

    #[Test]
    #[TestDox("Une gare pointée par le COMMERCIAL suffit : elle n'a pas besoin d'être réceptionnée")]
    public function pointageDuCommercialDebloqueLaSuite(): void
    {
        /*
            La garde cherche un PASSAGE horodaté, pas une réception. Une gare traversée sans agent est
            le cas ordinaire en brousse : le commercial à bord déclare la position, et cela suffit à
            laisser la suivante réceptionner. Exiger une réception aurait rendu la garde inapplicable
            là où elle sert le plus.
        */
        $this->demarrer();

        $this->avancer('Yamoussoukro', $this->admin);
        $this->assertStatut(200);

        $this->receptionner('Bouaké');
        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("Le COMMERCIAL non plus ne saute pas un arrêt")]
    public function avanceDuCommercialQuiSauteUnArretRefusee(): void
    {
        /*
            Même règle, même raison : la position commande ce qui est vendable, et une gare survolée
            se retrouve fermée à la vente sans que le car soit arrivé chez elle.

            La garde ne coûte RIEN à l'application du commercial : elle n'avance jamais que vers
            `prochainArret`, l'arrêt immédiatement suivant — un saut lui est impossible, en ligne
            comme hors ligne. Elle attrape donc ce qu'elle doit : l'appel direct à l'endpoint.
        */
        $this->demarrer();

        $this->avancer('Bouaké', $this->admin);

        $this->assertStatut(400);
        self::assertStringContainsString('Yamoussoukro', $this->messageErreur());
        self::assertSame(
            $this->reseau->gare('Abidjan')->getId(),
            $this->relire(Voyage::class, $this->voyageId)->getGarecourante()?->getId(),
            'la position ne bouge pas sur une avance refusée'
        );
    }

    #[Test]
    #[TestDox("Arrêt par arrêt, l'avance du commercial passe — y compris deux fois de suite")]
    public function avancesSuccessivesDuCommercial(): void
    {
        /*
            LE PIÈGE DU LOT HORS LIGNE, vérifié ici en ligne faute de pouvoir le rejouer autrement :
            la deuxième avance doit VOIR l'arrivée posée par la première. `PassageService` persiste
            SANS flusher et ne rattache pas le passage créé à `Voyage::getPassages()` — une garde qui
            lirait cette collection refuserait la seconde au motif que la première n'a jamais eu
            lieu. D'où la lecture par `PassageService::premierArretNonPointe`, qui consulte le cache
            de requête avant la base.
        */
        $this->demarrer();

        $this->avancer('Yamoussoukro', $this->admin);
        $this->assertStatut(200);

        $this->avancer('Bouaké', $this->admin);
        $this->assertStatut(200);

        self::assertSame(
            $this->reseau->gare('Bouaké')->getId(),
            $this->relire(Voyage::class, $this->voyageId)->getGarecourante()?->getId()
        );
    }

    #[Test]
    #[TestDox("Le rattrapage d'un administrateur débloque la gare suivante")]
    public function rattrapageDebloque(): void
    {
        $this->demarrer();

        $this->rattraper('Yamoussoukro', '-30 minutes', $this->admin);
        $this->assertStatut(200);

        $this->receptionner('Bouaké');
        $this->assertStatut(200);
    }

    #[Test]
    #[TestDox("Le rattrapage ne réceptionne PAS les colis : la gare le fera elle-même")]
    public function rattrapageNeLivrePasLesColis(): void
    {
        /*
            S'il n'y avait personne pour pointer le car, il n'y avait probablement personne pour
            décharger. Déclarer le bagage « livré » depuis un bureau, c'est perdre la trace du seul
            objet qui compte ici : où est la valise.
        */
        $this->demarrer();

        $this->rattraper('Yamoussoukro', '-30 minutes', $this->admin);
        $this->assertStatut(200);

        self::assertSame(
            'EMBARQUE',
            $this->relire(Bagage::class, $this->bagageYamId)->getStatut(),
            'le bagage reste à bord tant que sa gare ne l\'a pas réceptionné'
        );
    }

    #[Test]
    #[TestDox("Un agent de gare ne rattrape pas le passage du voisin")]
    public function rattrapageReserveALAdmin(): void
    {
        $this->demarrer();

        $this->rattraper('Yamoussoukro', '-10 minutes', $this->agents['Bouaké']);

        $this->assertStatut(403);
    }

    #[Test]
    #[TestDox("Une heure qui ne s'insère pas dans la chronologie est refusée")]
    public function heureHorsSequenceRefusee(): void
    {
        /*
            Sans cette garde, le rattrapage rouvrirait par la porte de service le défaut que la garde
            d'ordre vient de fermer — et cette fois signé par un administrateur, donc au-dessus de
            tout soupçon.
        */
        $this->demarrer();

        $this->rattraper('Yamoussoukro', '-3 hours', $this->admin);

        $this->assertStatut(400);
        self::assertStringContainsString('postérieur', $this->messageErreur());
    }

    #[Test]
    #[TestDox('Un passage déjà consigné ne se rattrape pas')]
    public function passageDejaConsigne(): void
    {
        $this->demarrer();
        $this->receptionner('Yamoussoukro');
        $this->assertStatut(200);

        $this->rattraper('Yamoussoukro', '-5 minutes', $this->admin);

        $this->assertStatut(400);
        self::assertStringContainsString('déjà consigné', $this->messageErreur());
    }

    #[Test]
    #[TestDox("Le terminus ne se rattrape pas : c'est la clôture qui l'horodate")]
    public function terminusNonRattrapable(): void
    {
        $this->demarrer();

        $this->rattraper('Korhogo', '-5 minutes', $this->admin);

        $this->assertStatut(400);
        self::assertStringContainsString('terminus', $this->messageErreur());
    }

    private function demarrer(): void
    {
        $this->requete('PATCH', sprintf('/api/voyages/%d/demarrer', $this->voyageId), $this->agents['Abidjan'], []);
        $this->assertStatut(200);
        $this->reculerLeDepart();
    }

    /**
     * Recule le départ réel d'une heure.
     *
     * Le démarrage l'horodate à l'instant PRÉSENT, alors qu'un passage rattrapé est par nature
     * postérieur au départ et antérieur à maintenant : sans ce recul, aucune heure réaliste ne
     * tiendrait dans l'intervalle, et le test ne pourrait vérifier que des refus. Le passage de
     * l'origine suit, sinon il servirait de borne basse à la place du départ.
     */
    private function reculerLeDepart(): void
    {
        $depart = new DateTimeImmutable('-1 hour');
        $voyage = $this->relire(Voyage::class, $this->voyageId);
        $voyage->setDatedepartreelle($depart);
        foreach ($voyage->getPassages() as $passage) {
            if ($passage->getDepartReelle() !== null) {
                $passage->setDepartReelle($depart);
            }
        }
        $this->em->flush();
    }

    private function receptionner(string $gare): void
    {
        $this->requete('PATCH', sprintf('/api/voyages/%d/receptionner', $this->voyageId), $this->agents[$gare], []);
    }

    private function avancer(string $gare, User $acteur): void
    {
        $this->requete('PATCH', sprintf('/api/voyages/%d/avancer', $this->voyageId), $acteur, [
            'gare' => '/api/gares/' . $this->reseau->gare($gare)->getId(),
        ]);
    }

    private function rattraper(string $gare, string $quand, User $acteur): void
    {
        $this->requete('PATCH', sprintf('/api/voyages/%d/rattraper-passage', $this->voyageId), $acteur, [
            'gare' => '/api/gares/' . $this->reseau->gare($gare)->getId(),
            'arrivee' => (new DateTimeImmutable($quand))->format(DATE_ATOM),
        ]);
    }

    private function messageErreur(): string
    {
        $r = $this->reponseJson();

        return (string) ($r['detail'] ?? $r['description'] ?? $r['hydra:description'] ?? '');
    }
}

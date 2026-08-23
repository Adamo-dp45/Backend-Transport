<?php

namespace App\Tests\Api;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Les voyages proposés au guichet pour la VENTE (`/api/voyages/reservables?usage=vente`).
 *
 * Un voyage RESTE vendable tant que le car n'a pas quitté la gare de l'agent — la date prévue n'est
 * pas le critère. Le terrain prend du retard, et un départ non clôturé qui disparaît du sélecteur
 * laisse l'agent sans aucun moyen de vendre le voyage qu'il a pourtant sous les yeux.
 */
final class VoyagesVendablesTest extends ApiTestCase
{
    private Reseau $reseau;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké', 'Korhogo'], [null, 240, 180]);
    }

    #[Test]
    #[TestDox("Un voyage à venir est proposé à la vente")]
    public function voyageAVenir(): void
    {
        $voyage = $this->voyage(new DateTimeImmutable('+2 hours'));

        self::assertContains($voyage->getId(), $this->vendables(), 'cas nominal');
    }

    #[Test]
    #[TestDox("Un voyage dont l'heure est passée mais qui n'est PAS parti reste vendable")]
    public function voyageEnRetardNonParti(): void
    {
        // Départ prévu il y a 3 heures, jamais marqué comme parti, jamais clôturé : le car est
        // encore à quai, l'agent doit pouvoir continuer à vendre.
        $voyage = $this->voyage(new DateTimeImmutable('-3 hours'));

        self::assertContains($voyage->getId(), $this->vendables());
    }

    #[Test]
    #[TestDox("Un voyage en retard de plusieurs JOURS, non parti et non clôturé, reste vendable")]
    public function voyageEnRetardDePlusieursJours(): void
    {
        // C'est le cas signalé : le départ traîne (véhicule immobilisé, ligne suspendue…), personne
        // ne l'a clôturé, et il sort quand même du sélecteur.
        $voyage = $this->voyage(new DateTimeImmutable('-5 days'));

        self::assertContains(
            $voyage->getId(),
            $this->vendables(),
            "seul le passage du car doit fermer la vente, jamais l'ancienneté de la date prévue"
        );
    }

    #[Test]
    #[TestDox("Un voyage clôturé n'est plus vendable")]
    public function voyageCloture(): void
    {
        $voyage = $this->voyage(new DateTimeImmutable('-3 hours'));
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-3 hours'))
            ->setDatearriveereelle(new DateTimeImmutable('-1 hour'));
        $this->em->flush();

        self::assertNotContains($voyage->getId(), $this->vendables());
    }

    #[Test]
    #[TestDox("Un voyage dont le car a quitté la gare de l'agent n'est plus vendable")]
    public function carRepartiDeLaGareDeLAgent(): void
    {
        $voyage = $this->voyage(new DateTimeImmutable('-3 hours'));
        $voyage
            ->setDatedepartreelle(new DateTimeImmutable('-3 hours'))
            ->setGarecourante($this->reseau->gare('Bouaké'));
        $this->em->flush();

        // L'agent est à Abidjan, le car est déjà à Bouaké : plus rien à vendre ici.
        self::assertNotContains($voyage->getId(), $this->vendables());
    }

    #[Test]
    #[TestDox("Un départ partiel n'est pas proposé aux gares situées avant sa provenance")]
    public function departPartielNonProposeEnAmont(): void
    {
        // Le car part de Bouaké : il ne passe jamais par Abidjan. Le proposer au guichet d'Abidjan
        // conduirait l'agent à une vente que 'TicketProcessor' refuse (« aucune vente possible
        // avant cette gare ») — proposer un départ que la création refuse est pire que rien.
        $partiel = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 30),
            depart: new DateTimeImmutable('+2 hours'),
            provenance: 'Bouaké'
        );

        self::assertNotContains($partiel->getId(), $this->vendables());
    }

    #[Test]
    #[TestDox("Le même départ partiel reste proposé à sa gare de provenance")]
    public function departPartielProposeAsaProvenance(): void
    {
        $partiel = $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 30),
            depart: new DateTimeImmutable('+2 hours'),
            provenance: 'Bouaké'
        );

        $agentBouake = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Bouaké')
        );
        $this->requete('GET', '/api/voyages/reservables?usage=vente', $agentBouake);
        $this->assertStatut(200);

        $ids = array_map(static fn (array $v): int => (int) $v['id'], $this->reponseJson()['member'] ?? []);
        self::assertContains($partiel->getId(), $ids);
    }

    #[Test]
    #[TestDox("Les voyages se récupèrent par identifiant, sans dépendre de la pagination")]
    public function recuperationParIdentifiant(): void
    {
        // Le sélecteur de vente demande au serveur QUELS voyages sont vendables, puis vient
        // chercher CEUX-LÀ en entier. Sans filtre 'id', il chargeait la première page de
        // '/api/voyages' (25, triés par date de création décroissante) et croisait en mémoire :
        // au-delà de 25 voyages ouverts, les plus anciennement créés — donc les plus imminents —
        // n'étaient jamais dans la liste croisée.
        $vise = $this->voyage(new DateTimeImmutable('+2 hours'));
        $autre = $this->voyage(new DateTimeImmutable('+5 hours'));
        $this->voyage(new DateTimeImmutable('+9 hours'));

        $agent = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan')
        );

        $this->requete('GET', sprintf('/api/voyages?id[]=%d&id[]=%d', $vise->getId(), $autre->getId()), $agent);

        $this->assertStatut(200);
        $ids = array_map(static fn (array $v): int => (int) $v['id'], $this->reponseJson()['member'] ?? []);
        sort($ids);

        $attendus = [$vise->getId(), $autre->getId()];
        sort($attendus);
        self::assertSame($attendus, $ids);
    }

    /** @return list<int> identifiants des voyages proposés à l'agent d'Abidjan */
    private function vendables(): array
    {
        $agent = $this->scenario->utilisateur(
            $this->reseau->entreprise,
            gare: $this->reseau->gare('Abidjan')
        );

        $this->requete('GET', '/api/voyages/reservables?usage=vente', $agent);
        $this->assertStatut(200);

        return array_map(
            static fn (array $v): int => (int) $v['id'],
            $this->reponseJson()['member'] ?? []
        );
    }

    private function voyage(DateTimeImmutable $depart): \App\Entity\Voyage
    {
        return $this->scenario->voyage(
            $this->reseau,
            car: $this->scenario->car($this->reseau->entreprise, 30),
            depart: $depart
        );
    }
}

<?php

namespace App\Tests\Domain;

use App\Domain\Enum\SessioncaisseStatut;
use App\Domain\Service\AlerteGenerationService;
use App\Entity\Alerte;
use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Reseau;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * LES DEUX ALERTES DE CAISSE, et surtout CE QU'ELLES NE DOIVENT PAS SIGNALER.
 *
 * Une alerte qui se trompe trois fois cesse d'être lue, et le module entier avec elle. Deux pièges
 * sont testés ici pour cette raison :
 *  - une caisse ouverte ce matin n'est PAS une caisse oubliée (sinon l'agent de nuit la déclenche
 *    à chaque prise de poste) ;
 *  - un écart calculé sur un attendu NÉGATIF vaut la totalité d'un réapprovisionnement non saisi,
 *    donc plusieurs dizaines de milliers de francs — le signaler ferait sonner l'anti-fraude sur
 *    un chiffre dont l'écran dit lui-même qu'il ne mesure rien.
 */
final class AlerteCaisseTest extends ApiTestCase
{
    private Reseau $reseau;

    private AlerteGenerationService $generateur;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reseau = $this->scenario->reseau(['Abidjan', 'Bouaké'], [null, 240]);
        $this->agent = $this->scenario->utilisateur($this->reseau->entreprise, gare: $this->reseau->gare('Abidjan'));
        $this->generateur = static::getContainer()->get(AlerteGenerationService::class);
    }

    #[Test]
    #[TestDox('Une caisse ouverte depuis la veille est signalée, celle de ce matin non')]
    public function caisseNonCloturee(): void
    {
        $this->caisse(ouverteIlYA: '-30 hours');
        $this->caisse(ouverteIlYA: '-2 hours');

        $this->generateur->genererPourEntreprise($this->reseau->entreprise);

        $alertes = $this->alertes('CAISSE_NON_CLOTUREE');
        self::assertCount(
            1,
            $alertes,
            "seule la caisse de la veille est oubliée : celle de ce matin est un agent qui travaille"
        );
        self::assertStringContainsString('30 heures', $alertes[0]->getMessage());
        self::assertSame(
            $this->reseau->gare('Abidjan')->getId(),
            $alertes[0]->getIdgare(),
            'portée GARE : c\'est le chef de gare qui ira chercher l\'agent'
        );
    }

    #[Test]
    #[TestDox("Un manquant important est signalé à la direction, un petit écart non")]
    public function ecartEleve(): void
    {
        $this->caisse(cloturee: true, theorique: 50000, compte: 20000, motif: 'Vol présumé');
        $this->caisse(cloturee: true, theorique: 50000, compte: 49000, motif: 'Monnaie rendue');

        $this->generateur->genererPourEntreprise($this->reseau->entreprise);

        $alertes = $this->alertes('CAISSE_ECART_ELEVE');
        self::assertCount(1, $alertes, 'un écart de 1 000 se règle entre l\'agent et son chef');
        self::assertStringContainsString('-30 000', str_replace("\u{202f}", ' ', $alertes[0]->getMessage()));
        self::assertStringContainsString(
            'Vol présumé',
            $alertes[0]->getMessage(),
            "le motif est DANS le message : une alerte qui oblige à ouvrir la fiche pour savoir si "
            . "l'agent s'est déjà expliqué sera ouverte une fois, puis ignorée"
        );
        self::assertNull($alertes[0]->getIdgare(), 'portée DIRECTION : elle ne passe pas par la gare visée');
    }

    #[Test]
    #[TestDox("Un écart calculé sur un attendu NÉGATIF n'est jamais signalé")]
    public function attenduNegatifIgnore(): void
    {
        // Le cas réel : un remboursement payé avec des espèces venues d'ailleurs, non saisies.
        $this->caisse(cloturee: true, theorique: -30000, compte: 10000, motif: 'Remboursement sur le coffre');

        $this->generateur->genererPourEntreprise($this->reseau->entreprise);

        self::assertCount(
            0,
            $this->alertes('CAISSE_ECART_ELEVE'),
            "l'écart vaut ici +40 000 alors que l'agent n'a rien de trop : le signaler apprendrait "
            . "aux chefs de gare à ignorer cette alerte"
        );
    }

    private function caisse(
        string $ouverteIlYA = '-1 hour',
        bool $cloturee = false,
        ?int $theorique = null,
        ?int $compte = null,
        ?string $motif = null,
    ): Sessioncaisse {
        $session = (new Sessioncaisse())
            ->setAgent($this->agent)
            ->setGare($this->reseau->gare('Abidjan'))
            ->setDatedebut(new DateTimeImmutable($ouverteIlYA))
            ->setFondsouverture(0)
            ->setStatut($cloturee ? SessioncaisseStatut::CLOTUREE->value : SessioncaisseStatut::OUVERTE->value);

        $session->setIdentreprise($this->reseau->identreprise());

        if ($cloturee) {
            $session
                ->setDatefin(new DateTimeImmutable('-1 hour'))
                ->setMontanttheorique($theorique)
                ->setMontantcompte($compte)
                ->setEcart((int) $compte - (int) $theorique)
                ->setMotifecart($motif);
        } else {
            // La colonne de repli porte l'index unique : une seule caisse OUVERTE par agent.
            $session->setAgentsessionouverte(null);
        }

        $this->em->persist($session);
        $this->em->flush();

        return $session;
    }

    /** @return list<Alerte> */
    private function alertes(string $type): array
    {
        return $this->em->getRepository(Alerte::class)->findBy([
            'identreprise' => $this->reseau->identreprise(),
            'type' => $type,
        ]);
    }
}

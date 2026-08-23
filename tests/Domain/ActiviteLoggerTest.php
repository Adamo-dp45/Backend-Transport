<?php

namespace App\Tests\Domain;

use App\Domain\Service\ActiviteLogger;
use App\Entity\Activite;
use App\Entity\Client;
use App\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * La règle du journal : **une trace qui échoue ne fait jamais échouer le geste**.
 *
 * Journaliser est un service rendu à l'exploitation, pas une condition de la vente. Le risque que
 * ces tests couvrent est asymétrique : une activité perdue se constate dans les logs, tandis qu'une
 * vente refusée parce que sa ligne de journal n'a pas pu s'écrire est une panne — et une panne
 * incompréhensible pour l'agent, qui ne voit aucun rapport entre son billet et un journal.
 */
final class ActiviteLoggerTest extends IntegrationTestCase
{
    private ActiviteLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->service(ActiviteLogger::class);
    }

    #[Test]
    #[TestDox('Une valeur hors gabarit ne remonte pas : elle est bornée, pas rejetée')]
    public function valeurHorsGabaritNeRemontePas(): void
    {
        $this->connecter();

        // 'type' est un VARCHAR(60), 'libelle' un VARCHAR(255) : sans bornage, l'INSERT ferait
        // tomber le flush de l'appelant — donc la vente elle-même.
        $this->logger->log(str_repeat('T', 300), str_repeat('L', 1000), str_repeat('C', 200), 1);
        $this->em->flush();

        $activite = $this->em->getRepository(Activite::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($activite, 'la trace doit exister, tronquée');
        self::assertSame(60, mb_strlen((string) $activite->getType()));
        self::assertSame(255, mb_strlen((string) $activite->getLibelle()));
        self::assertSame(50, mb_strlen((string) $activite->getCibletype()));
    }

    #[Test]
    #[TestDox("Hors contexte utilisateur (CLI), journaliser ne lève rien et n'écrit rien")]
    public function horsContexteUtilisateur(): void
    {
        $avant = $this->nombreActivites();

        $this->logger->log(ActiviteLogger::TICKET_ANNULE, 'Sans utilisateur connecté');
        $this->em->flush();

        self::assertSame($avant, $this->nombreActivites(), 'aucune trace sans acteur : rien à imputer');
    }

    #[Test]
    #[TestDox("Le geste métier aboutit même quand la trace est impossible à préparer")]
    public function leGesteAboutitQuandLaTraceEstImpossible(): void
    {
        /*
            On simule la panne la plus réaliste : l'entité 'Activite' devient inconstructible. Un
            EntityManager FERMÉ fait lever 'persist()' — c'est exactement le genre d'incident qui,
            avant, remontait jusqu'au processor et faisait échouer la vente.
        */
        $this->connecter();
        $this->em->close();

        $this->logger->log(ActiviteLogger::TICKET_ANNULE, 'Trace impossible');

        // Le seul résultat attendu : on est encore là. Aucune exception n'a traversé.
        self::assertTrue(true, 'log() a absorbé la panne au lieu de la propager à l\'appelant');
    }

    #[Test]
    #[TestDox('Un identifiant de cible hors bornes est neutralisé, pas propagé à la base')]
    public function identifiantHorsBornesNeutralise(): void
    {
        $this->connecter();

        // Le geste métier : un client enregistré au guichet. C'est LUI qui ne doit pas disparaître.
        $client = (new Client())->setNom('Konan Aya')->setContact('+225 07 00 00 00 42')->setFidelite(false);
        $this->em->persist($client);

        /*
            'cibleid' est un INT MySQL et le serveur tourne en STRICT_TRANS_TABLES : une valeur
            au-delà de 2 147 483 647 est REFUSÉE à l'insertion. Aucun garde-fou PHP ne la voit passer.

            Comme la trace partage — volontairement — la transaction de l'appelant, ce refus emportait
            le geste avec lui : le client n'était pas enregistré. La parade n'est pas de sortir la
            trace de la transaction (on y perdrait la garantie inverse, bien plus précieuse : ne jamais
            attester un geste qui n'a pas eu lieu), mais de retirer à l'INSERT ses raisons d'échouer.
        */
        $this->logger->log(ActiviteLogger::TICKET_ANNULE, 'Cible hors bornes', 'Voyage', PHP_INT_MAX);

        $this->em->flush();

        self::assertNotNull(
            $client->getId(),
            'le client devait être enregistré : une ligne de journal ne décide pas du sort d\'une vente'
        );
        $trace = $this->em->getRepository(Activite::class)->findOneBy(['libelle' => 'Cible hors bornes']);
        self::assertNotNull($trace, 'la trace reste écrite : c\'est la CIBLE qui est perdue, pas le geste');
        self::assertNull($trace->getCibleid(), 'un identifiant inexploitable vaut mieux nul qu\'un INSERT refusé');
    }

    // --------------------------------------------------------------------------------------------

    private function connecter(): void
    {
        $entreprise = $this->scenario->entreprise();
        $user = $this->scenario->utilisateur($entreprise);

        $this->service(\Symfony\Bundle\SecurityBundle\Security::class);
        $jeton = new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
            $user,
            'main',
            $user->getRoles()
        );
        $this->service(\Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface::class)
            ->setToken($jeton);
    }

    private function nombreActivites(): int
    {
        return (int) $this->em->getRepository(Activite::class)
            ->createQueryBuilder('a')->select('COUNT(a.id)')
            ->getQuery()->getSingleScalarResult();
    }
}

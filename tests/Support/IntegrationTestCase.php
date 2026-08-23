<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Socle des tests d'intégration des services métier : conteneur réel, vraie base.
 *
 * POURQUOI UNE VRAIE BASE plutôt que des doubles : les règles testées ici (capacité, éviction,
 * échéances, recette) vivent dans des requêtes Doctrine. Simuler les repositories reviendrait à
 * vérifier que les doubles renvoient ce qu'on leur a demandé de renvoyer — le jour où une clause
 * SQL change, le test resterait vert. Ce sont précisément ces requêtes qu'on veut protéger.
 *
 * L'isolation est décrite dans {@see BaseDeDonneesTestTrait}.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    use BaseDeDonneesTestTrait;

    protected EntityManagerInterface $em;

    protected ScenarioBuilder $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->ouvrirTransactionDeTest($this->em->getConnection());

        $this->scenario = new ScenarioBuilder($this->em);
    }

    protected function tearDown(): void
    {
        // Avant 'parent::tearDown()' : l'arrêt du noyau ferme la connexion, et l'annulation
        // n'aurait plus lieu.
        $this->annulerTransactionDeTest($this->em->getConnection());

        // Sans cela, l'EntityManager d'un test fuit sur le suivant et fait resurgir des entités
        // détachées pointant vers des lignes qui n'existent plus.
        $this->em->clear();

        parent::tearDown();
    }

    /**
     * Récupère un service du conteneur de test.
     *
     * @template T of object
     * @param class-string<T> $service
     * @return T
     */
    protected function service(string $service): object
    {
        return static::getContainer()->get($service);
    }
}

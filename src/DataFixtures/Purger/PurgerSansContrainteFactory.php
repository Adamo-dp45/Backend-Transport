<?php

namespace App\DataFixtures\Purger;

use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\Common\DataFixtures\Purger\PurgerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Décore la fabrique de purger du bundle pour livrer un purger tolérant au cycle
 * 'ticket' ↔ 'reservation' (cf. 'PurgerSansContrainte').
 *
 * On DÉCORE plutôt que d'enregistrer une fabrique concurrente sous l'alias « default » : l'alias
 * gagnant dépendrait alors de l'ordre de découverte des services. La décoration, elle, remplace la
 * fabrique de façon déterministe — et le tag (donc l'alias « default ») suit automatiquement le
 * décorateur. Résultat : 'doctrine:fixtures:load' fonctionne sans option supplémentaire.
 */
final class PurgerSansContrainteFactory implements PurgerFactory
{
    public function __construct(
        private PurgerFactory $factory
    ) {
    }

    public function createForEntityManager(
        string|null $emName,
        EntityManagerInterface $em,
        array $excluded = [],
        bool $purgeWithTruncate = false,
    ): PurgerInterface {
        return new PurgerSansContrainte(
            $this->factory->createForEntityManager($emName, $em, $excluded, $purgeWithTruncate),
            $em
        );
    }
}

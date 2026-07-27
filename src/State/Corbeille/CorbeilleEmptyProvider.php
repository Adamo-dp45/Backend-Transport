<?php

namespace App\State\Corbeille;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Data\Corbeille;

/**
 * Corbeille étant une ressource NON persistée, API Platform n'a rien à charger pour les opérations
 * d'action ({type}/{id} et lots). On fournit un objet vide : le traitement réel se fait dans le
 * processor via $uriVariables / la query string.
 */
final class CorbeilleEmptyProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Corbeille
    {
        return new Corbeille();
    }
}

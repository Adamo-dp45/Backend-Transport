<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Data\Corbeille;

class CorbeilleEmptyProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new Corbeille(); /*
            - On retourne un objet vide pour satisfaire 'ApiPlatform', les données seront traitées dans le 'processor' via '$uriVariables', ou.. return null
        */
    }
}

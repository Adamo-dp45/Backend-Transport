<?php

namespace App\State\Corbeille;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\CorbeilleService;

/**
 * DELETE /api/corbeille/{type}/{id} — suppression DÉFINITIVE (purge) d'un élément en corbeille.
 * 204 en cas de succès ; 422 si l'élément est encore référencé (contrainte FK).
 */
final class CorbeillePurgerProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorbeilleService $corbeille
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $this->corbeille->purger((string)$uriVariables['type'], (int)$uriVariables['id']);

        return null;
    }
}

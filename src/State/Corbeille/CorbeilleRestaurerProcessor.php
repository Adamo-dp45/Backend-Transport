<?php

namespace App\State\Corbeille;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\CorbeilleService;

/**
 * PATCH /api/corbeille/{type}/{id}/restaurer — remet un élément en service (deletedAt = null).
 */
final class CorbeilleRestaurerProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CorbeilleService $corbeille,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $this->corbeille->restaurer((string) $uriVariables['type'], (int) $uriVariables['id']);

        return ['message' => 'Élément restauré avec succès.'];
    }
}

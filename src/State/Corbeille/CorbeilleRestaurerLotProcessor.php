<?php

namespace App\State\Corbeille;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\CorbeilleService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * POST /api/corbeille/restaurer-tout — restaure en lot (filtres ?type[]=&entreprise=, sinon tout).
 */
final class CorbeilleRestaurerLotProcessor implements ProcessorInterface
{
    use CorbeilleFiltresTrait;

    public function __construct(
        private readonly CorbeilleService $corbeille,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        [$types, $entreprise] = $this->filtres($this->requestStack->getCurrentRequest());
        $count = $this->corbeille->restaurerLot($types, $entreprise);

        return ['message' => $count . ' élément(s) restauré(s).', 'count' => $count];
    }
}

<?php

namespace App\State\Corbeille;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\CorbeilleService;
use App\Entity\Output\Corbeille\CorbeilleListeDto;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GET /api/corbeille — vue d'ensemble (compteurs + éléments), filtres ?type[]=&entreprise=.
 */
final class CorbeilleProvider implements ProviderInterface
{
    use CorbeilleFiltresTrait;

    public function __construct(
        private readonly CorbeilleService $corbeille,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CorbeilleListeDto
    {
        [$types, $entreprise] = $this->filtres($this->requestStack->getCurrentRequest());

        return $this->corbeille->lister($types, $entreprise);
    }
}

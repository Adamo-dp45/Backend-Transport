<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Entity\Output\Reservation\GarePubliqueDto;
use App\Repository\GareRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** Gares actives d'une ville (points de provenance) — `?slug=&ville=`. */
final class GaresPubliquesProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private GareRepository $gareRepository
    )
    {
    }

    /** @return GarePubliqueDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $e = $this->resolver->resoudre($request?->query->get('slug'));
        $villeId = (int) $request?->query->get('ville');
        if (!$villeId) {
            return [];
        }

        return array_map(
            fn($g) => new GarePubliqueDto(id: $g->getId(), libelle: (string) $g->getLibelle(), ville: $g->getVille()?->getNom()),
            $this->gareRepository->findActivesParVille($villeId, $e->getId())
        );
    }
}

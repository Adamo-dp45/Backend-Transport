<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Entity\Output\Reservation\VillePubliqueDto;
use App\Repository\VilleRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** Villes desservies (≥1 gare active) d'une compagnie — `?slug=`. */
final class VillesPubliquesProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private VilleRepository $villeRepository
    )
    {
    }

    /** @return VillePubliqueDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $slug = $this->requestStack->getCurrentRequest()?->query->get('slug');
        $e = $this->resolver->resoudre($slug);

        return array_map(
            fn($v) => new VillePubliqueDto(id: $v->getId(), nom: (string) $v->getNom()),
            $this->villeRepository->findPourNavigation($e->getId())
        );
    }
}

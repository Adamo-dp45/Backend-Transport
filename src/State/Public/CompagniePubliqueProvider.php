<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Domain\Service\ReservationConfigService;
use App\Entity\Output\Reservation\CompagniePubliqueDto;
use Symfony\Component\HttpFoundation\RequestStack;

/** Branding public d'une compagnie, résolue par `?slug=`. */
final class CompagniePubliqueProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private ReservationConfigService $config
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CompagniePubliqueDto
    {
        $slug = $this->requestStack->getCurrentRequest()?->query->get('slug');
        $e = $this->resolver->resoudre($slug);

        return new CompagniePubliqueDto(
            slug: (string) $e->getSlug(),
            libelle: $e->getLibelle(),
            sigle: $e->getSigle(),
            contact: $e->getContact1(),
            siteweb: $e->getSiteweb(),
            delaiPaiementMinutes: $this->config->getParametre((int) $e->getId())->getDelaiPaiementMinutes()
        );
    }
}

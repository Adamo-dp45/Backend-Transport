<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Enum\ReferenceStatus;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Entity\Output\Reservation\DestinationPubliqueDto;
use App\Entity\Output\Reservation\GarePubliqueDto;
use App\Repository\TarifRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/** Destinations desservies depuis une gare + tarif (grille tarifaire) — `?slug=&gare=`. */
final class DestinationsPubliquesProvider implements ProviderInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private TarifRepository $tarifRepository
    )
    {
    }

    /** @return DestinationPubliqueDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $e = $this->resolver->resoudre($request?->query->get('slug'));
        $gareId = (int) $request?->query->get('gare');
        if (!$gareId) {
            return [];
        }

        $destinations = [];
        foreach ($this->tarifRepository->findDepuisGare($gareId, $e->getId()) as $tarif) {
            $dest = $tarif->getGarearrivee();
            if ($dest === null || $dest->getDeletedAt() !== null || $dest->getStatut() !== ReferenceStatus::ACTIF->value) {
                continue;
            }
            $destinations[] = new DestinationPubliqueDto(
                gare: new GarePubliqueDto(id: $dest->getId(), libelle: (string) $dest->getLibelle(), ville: $dest->getVille()?->getNom()),
                montant: (int) $tarif->getMontant()
            );
        }

        return $destinations;
    }
}

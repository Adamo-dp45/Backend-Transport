<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Depannage;
use App\Repository\DepannageRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Décore les providers Doctrine du Dépannage pour renseigner les agrégats de PIÈCES
 * ('detaildepannagesCount' et 'piecesQuantiteTotale') en UNE requête (COUNT + SUM groupés),
 * au lieu de sérialiser la collection 'detaildepannages' entière pour que le client la somme.
 *
 * On CONSERVE tout le pipeline ApiPlatform (filtres, tri, pagination, EntrepriseScopeExtension…) :
 * on se contente d'enrichir les entités renvoyées, sans remplacer la récupération.
 */
final class DepannageProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly DepannageRepository $depannageRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);

            // '$data' est un Paginator Doctrine : on peut l'itérer sans le détruire (mêmes instances).
            $depannages = iterator_to_array($data);
            $agregats = $this->depannageRepository->piecesParDepannageIds(
                array_map(fn(Depannage $d) => $d->getId(), $depannages)
            );
            foreach ($depannages as $depannage) {
                $this->appliquer($depannage, $agregats);
            }

            return $data; // paginator d'origine (pagination préservée), entités enrichies
        }

        $depannage = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($depannage instanceof Depannage) {
            $this->appliquer(
                $depannage,
                $this->depannageRepository->piecesParDepannageIds([$depannage->getId()])
            );
        }

        return $depannage;
    }

    /** @param array<int, array{nombre:int, quantite:int}> $agregats */
    private function appliquer(Depannage $depannage, array $agregats): void
    {
        $vals = $agregats[$depannage->getId()] ?? ['nombre' => 0, 'quantite' => 0];
        $depannage
            ->setDetaildepannagesCount($vals['nombre'])
            ->setPiecesQuantiteTotale($vals['quantite']);
    }
}

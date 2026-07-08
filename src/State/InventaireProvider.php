<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Inventaire;
use App\Entity\Output\Inventaire\InventaireOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class InventaireProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $isCollection = $operation instanceof GetCollection;
        $data = $isCollection
            ? $this->collectionProvider->provide($operation, $uriVariables, $context)
            : $this->itemProvider->provide($operation, $uriVariables, $context)
        ; /*
            - On conserve le pipeline de 'ApiPlatform' ce qui applqiue les filtres comme 'EntrepriseScopeExtension'..
        */
        if($isCollection) {
            $items = iterator_to_array($data); /*
                - Vu que '$data' est un 'Paginator Doctrine' on peut itérer sans le détruire
            */
            // L'auteur est une relation EAGER → déjà chargé (une requête groupée, pas de N+1)
            $mapped = array_map(
                fn(Inventaire $i) => $this->toOutput($i),
                $items
            );

            $currentPage = 1;
            $itemsPerPage = count($mapped);
            $totalItems = count($mapped);

            if($data instanceof PartialPaginatorInterface) {
                $currentPage = $data->getCurrentPage();
                $itemsPerPage = $data->getItemsPerPage();
            }

            if($data instanceof PaginatorInterface) {
                $totalItems = $data->getTotalItems();
            }

            return new TraversablePaginator( /*
                - On réemballe dans un 'TraversablePaginator' pour conserver la pagination
            */
                new \ArrayIterator($mapped),
                $currentPage,
                $itemsPerPage,
                $totalItems
            );
        }

        if(!$data instanceof Inventaire) {
            return null;
        }

        return $this->toOutput($data);
    }

    private function toOutput(Inventaire $inventaire): InventaireOutput
    {
        $auteur = $inventaire->getAuteur();
        return new InventaireOutput(
            id: $inventaire->getId(),
            typemouvement: $inventaire->getTypemouvement(),
            referencetype: $inventaire->getReferenceType(),
            referenceid: $inventaire->getReferenceId(),
            quantite: $inventaire->getQuantite(),
            datemouvement: $inventaire->getDatemouvement()?->format('Y-m-d H:i') ?? '',
            createdAt: $inventaire->getCreatedAt()?->format('Y-m-d H:i') ?? '',
            pieceName: $inventaire->getPiece()?->getLibelle(),
            createdBy: $inventaire->getCreatedBy(),
            createdByNom: $auteur?->getNom(),
            createdByPrenom: $auteur?->getPrenom()
        );
    }
}

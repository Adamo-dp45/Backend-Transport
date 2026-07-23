<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Client;
use App\Repository\ClientRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Décore les providers Doctrine du Client pour renseigner 'bagagesCount' en UNE requête (DQL COUNT
 * joignant billets→bagages) au lieu d'un décompte par entité qui hydraterait les billets (N+1).
 * On CONSERVE tout le pipeline ApiPlatform (filtres, tri, pagination, EntrepriseScopeExtension…) :
 * on se contente d'enrichir les entités renvoyées, sans remplacer la récupération.
 */
final class ClientProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly ClientRepository $clientRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);

            // '$data' est un Paginator Doctrine : on peut l'itérer sans le détruire (mêmes instances).
            $clients = iterator_to_array($data);
            $counts = $this->clientRepository->bagagesCountByClientIds(
                array_map(fn(Client $c) => $c->getId(), $clients)
            );
            foreach ($clients as $client) {
                $client->setBagagesCount($counts[$client->getId()] ?? 0);
            }

            return $data; // on renvoie le paginator d'origine (pagination préservée), entités enrichies
        }

        $client = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($client instanceof Client) {
            $counts = $this->clientRepository->bagagesCountByClientIds([$client->getId()]);
            $client->setBagagesCount($counts[$client->getId()] ?? 0);
        }

        return $client;
    }
}

<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\User;
use App\Security\UserManagementGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture d'un utilisateur / d'une liste, enrichie du repère GÉRABLE.
 *
 * On se BRANCHE au pipeline natif d'API Platform (on ne le remplace pas) : filtres, tri, pagination
 * et extensions de périmètre s'appliquent normalement — on se contente de poser un repère sur les
 * entités renvoyées, comme {@see TicketProvider} le fait pour 'evince' et 'modifiable'.
 *
 * POURQUOI : la règle « qui peut gérer qui » vit dans 'UserManagementGuard', mais elle était
 * REDITE deux fois côté front — une fois correctement dans le tableau React (dont le commentaire
 * assumait être un « miroir frontend du guard »), une fois INCOMPLÈTEMENT dans la fiche, où il
 * manquait l'auto-gestion : un utilisateur voyait « Modifier le profil et les rôles » sur sa propre
 * fiche, remplissait le formulaire, et découvrait le refus à l'enregistrement.
 *
 * Trois copies d'une règle de sécurité dérivent toujours. Le serveur tranche, le front lit.
 */
class UserProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly UserManagementGuard $guard,
        private readonly Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $acteur = $this->security->getUser();

        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);
            foreach ($data as $utilisateur) {
                if ($utilisateur instanceof User) {
                    $this->marquer($utilisateur, $acteur);
                }
            }

            // Paginator renvoyé INTACT : on mute les entités déjà chargées, rien à reconstruire.
            return $data;
        }

        $data = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($data instanceof User) {
            $this->marquer($data, $acteur);
        }

        return $data;
    }

    private function marquer(User $cible, ?object $acteur): void
    {
        $cible->setGerable($acteur instanceof User && $this->guard->canManage($acteur, $cible));
    }
}

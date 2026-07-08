<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Résiliation de l'adhésion fidélité : désactive le membre. Le n° de carte et la date d'adhésion
 * sont CONSERVÉS (trace historique) ; une ré-adhésion réutilise la même carte.
 */
class ResilierFideliteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        /** @var Client $data */
        if (!$data->isFidelite()) {
            throw new BadRequestHttpException('Ce client n\'est pas membre du programme de fidélité');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $data
            ->setFidelite(false)
            ->setUpdatedBy($user->getId());

        $this->em->flush();

        return $data;
    }
}

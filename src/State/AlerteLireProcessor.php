<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\AlerteStatut;
use App\Entity\Alerte;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Action « marquer lu » (PATCH /alertes/{id}/lire) : acquitte l'alerte (statut LUE + auteur/date).
 * L'item est déjà borné à l'audience de l'utilisateur (AlerteAudienceExtension applyToItem), donc
 * on ne peut acquitter qu'une alerte qu'on a le droit de voir. Idempotent : re-lire une LUE ne fait rien.
 */
class AlerteLireProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Alerte $data */
        if ($data->getStatut() === AlerteStatut::RESOLUE->value) {
            throw new BadRequestHttpException('Alerte déjà résolue.');
        }

        if ($data->getStatut() !== AlerteStatut::LUE->value) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            $data->setStatut(AlerteStatut::LUE->value)
                ->setLuePar($user?->getId())
                ->setLueLe(new \DateTimeImmutable());
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

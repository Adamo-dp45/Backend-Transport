<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Trace l'auteur d'une modification ('updatedBy') — processeur GÉNÉRIQUE, partagé par les référentiels
 * et entités simples (villes, gares, tarifs, types, clients, fournisseurs…).
 *
 * Il ne porte AUCUNE règle propre à une entité : la modification d'un BILLET, qui exige ses propres
 * gardes (voyage clôturé, gare émettrice, car déjà passé à la gare de montée) et la re-résolution du
 * Client quand le téléphone change, passe par 'TicketUpdateProcessor'.
 */
class UpdatedbyProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();

        if(!$data instanceof EntrepriseOwnedInterface) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }
        $data->setUpdatedBy($user->getId());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

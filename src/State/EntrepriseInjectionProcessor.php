<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class EntrepriseInjectionProcessor implements ProcessorInterface
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
        $user = $this->security->getUser(); /*
            - Pas besoin de vérifié si l'utilisateur existe à cause du voter
        */
        if(!$data instanceof EntrepriseOwnedInterface) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }
        $data
            ->setIdentreprise($user->getEntreprise()->getId())
            ->setCreatedBy($user->getId()); /*
            - On a pas besoin de 'flush' vu qu'il est fait automatiquement via '->process()'
        */
        /*
            - Ou..
                $metadata = $context['resource_class'] ? $context['resource_class'] : get_class($data);
                $classMetadata = $context['entity_manager']->getClassMetadata($metadata);
                if($classMetadata->hasField('identreprise')) -- Ou.. if(method_exists($data, 'setIdentreprise'))

            - On n'a exclus la condition à cause du 'EntrepriseScopeExtension'
                if($data->getIdentreprise() !== $user->getEntreprise()->getId())
        */
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

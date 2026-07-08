<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Personnel;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class PersonnelProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Personnel $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();
        $data
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId())
        ;
        /*
            $count = $this->personnelRepository->count([
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]) + 1;
            $code = sprintf('PER-%d-%04d', $entrepriseId, $count);
            $data->setCode($code);
        */
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

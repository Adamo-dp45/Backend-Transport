<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DesactiverEntrepriseProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private readonly Security $security,
        private EntrepriseRepository $entrepriseRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Entreprise $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entreprise = $this->entrepriseRepository->findOneBy([
            'id' => $uriVariables['id']
        ]);
        if(!$entreprise) {
            throw new NotFoundHttpException('Entreprise introuvable');
        }
        $entreprise
            ->setStatut(
                $data->getStatut() === ReferenceStatus::ACTIF->value
                ? ReferenceStatus::SUSPENDU->value
                : ReferenceStatus::ACTIF->value
            )
            ->setUpdatedBy($user->getId())
        ;
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

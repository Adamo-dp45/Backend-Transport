<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\FideliteService;
use App\Entity\Dto\ProgrammeFideliteInput;
use App\Entity\ProgrammeFidelite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Met à jour le programme de fidélité de l'entreprise courante (singleton auto-créé).
 */
class MeProgrammeFideliteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private FideliteService $fideliteService,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProgrammeFidelite
    {
        /** @var ProgrammeFideliteInput $data */
        /** @var User $user */
        $user = $this->security->getUser();

        $programme = $this->fideliteService->getProgramme($user->getEntreprise()->getId());
        $programme
            ->setSeuil($data->seuil)
            ->setRecompensePourcentage($data->recompensePourcentage)
            ->setActif($data->actif)
            ->setUpdatedBy($user->getId());

        $this->em->flush();

        return $programme;
    }
}

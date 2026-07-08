<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Dto\SupprimerMonCompteInput;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Suppression de compte par l'utilisateur lui-même : en réalité une SUSPENSION (réversible par un
 * admin), pas un effacement. On vérifie d'abord le mot de passe, puis on passe le compte en SUSPENDU.
 * Le 'UserChecker' empêchera ensuite toute nouvelle connexion.
 */
class SupprimerMonCompteProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserPasswordHasherInterface $hasher,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var SupprimerMonCompteInput $data */

        /** @var User $user */
        $user = $this->security->getUser();

        // Garde-fou : un super administrateur ne se supprime pas lui-même (risque de verrouiller l'entreprise).
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            throw new BadRequestHttpException('Un super administrateur ne peut pas supprimer son propre compte.');
        }

        if (!$this->hasher->isPasswordValid($user, $data->password)) {
            throw new BadRequestHttpException('Mot de passe incorrect.');
        }

        $user->setStatut(ReferenceStatus::SUSPENDU->value);

        return $this->processor->process($user, $operation, $uriVariables, $context);
    }
}

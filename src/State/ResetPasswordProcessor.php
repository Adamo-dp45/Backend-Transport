<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\PasswordResetTokenRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private PasswordResetTokenRepository $passwordResetTokenRepository,
        private UserPasswordHasherInterface $hasher
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $reset = $this->passwordResetTokenRepository->findOneBy([
            'token' => $data->token
        ]);

        if(!$reset) {
            throw new BadRequestHttpException('Token invalide');
        }

        if($reset->getUsedAt()) {
            throw new BadRequestHttpException('Token déjà utilisé');
        }

        if($reset->getExpiresAt() < new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Token expiré');
        }

        $user = $reset->getUser();
        $user->setPassword(
            $this->hasher->hashPassword($user, $data->password)
        );
        $reset->setUsedAt(new \DateTimeImmutable());

        return $this->processor->process($reset, $operation, $uriVariables, $context);
    }
}

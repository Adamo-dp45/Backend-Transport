<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Repository\PasswordResetTokenRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private PasswordResetTokenRepository $passwordResetTokenRepository,
        private UserPasswordHasherInterface $hasher,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.mot_de_passe_reinitialisation_ip')]
        private RateLimiterFactory $limiteurIp
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /*
            ANTI-ABUS. Le jeton fait 256 bits : il ne se devine pas, et cette limite ne protège
            donc PAS le secret — elle protège la ressource. Chaque appel déclenche un hachage de
            mot de passe, volontairement coûteux : quelques milliers de requêtes suffisent à
            occuper le processeur du serveur sans qu'aucune réinitialisation n'aboutisse.
        */
        if (!$this->limiteurIp->create($this->requestStack->getCurrentRequest()?->getClientIp() ?? 'anonymous')->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Trop de tentatives. Réessayez dans une heure.');
        }

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

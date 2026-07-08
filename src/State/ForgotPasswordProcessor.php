<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\MailerService;
use App\Entity\Dto\ForgotPasswordInput;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $passwordResetTokenRepository,
        private MailerService $mailer
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var ForgotPasswordInput $data */

        $user = $this->userRepository->findOneBy([
            'email' => $data->email
        ]);

        if(!$user) {
            return new Response(null, Response::HTTP_NO_CONTENT); /*
                - Ne jamais dire si email existe ou non donc on renvoi un '204' ou un '200' silencieux
            */ 
        }

        $this->passwordResetTokenRepository->invalidatePreviousTokens($user); /*
            - On invalide les anciens tokens non utilisés de l'utilisateur
        */
        $token = bin2hex(random_bytes(32));
        $reset = new PasswordResetToken();
        $reset
            ->setUser($user)
            ->setToken($token)
            ->setExpiresAt(new \DateTimeImmutable('+1 hour'))
        ;
        $link = rtrim($data->frontResetUrl, '/') . '?token=' . $token; /*
            - 'rtrim' évite d'avoir un double '/' si le frontend en envoie
        */
        $this->mailer->send($user->getEmail(), $link); /*
            - On peut le faire via un subscriber
        */
        return $this->processor->process($reset, $operation, $uriVariables, $context);
    }
}

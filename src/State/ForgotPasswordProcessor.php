<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\MailerService;
use App\Entity\Dto\ForgotPasswordInput;
use App\Entity\PasswordResetToken;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class ForgotPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $passwordResetTokenRepository,
        private MailerService $mailer,
        private RequestStack $requestStack,
        #[Autowire(service: 'limiter.mot_de_passe_oubli_ip')]
        private RateLimiterFactory $limiteurIp,
        #[Autowire(service: 'limiter.mot_de_passe_oubli_email')]
        private RateLimiterFactory $limiteurEmail
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var ForgotPasswordInput $data */

        /*
            ANTI-ABUS, AVANT TOUTE RECHERCHE DE COMPTE.

            Sans limite, cette route laissait deux choses : noyer la boîte d'une personne précise
            sous les courriels de réinitialisation, et balayer des milliers d'adresses pour deviner
            lesquelles existent. Le 204 silencieux ci-dessous empêche de lire la réponse, mais rien
            n'empêchait d'essayer en boucle.

            !! LE LIMITEUR PAR E-MAIL EST CONSOMMÉ POUR TOUTE ADRESSE SAISIE, existante ou non, et
            c'est tout l'intérêt de le placer ICI. Le déplacer après le 'findOneBy' ferait répondre
            429 sur une adresse connue et 204 sur une inconnue : l'énumération que le 204 sert
            justement à fermer, rouverte par le code de statut.
        */
        $requete = $this->requestStack->getCurrentRequest();
        $refus = 'Trop de demandes de réinitialisation. Réessayez dans une heure.';

        if (!$this->limiteurIp->create($requete?->getClientIp() ?? 'anonymous')->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: $refus);
        }

        // La casse ne doit pas servir de contournement : « Agent@… » et « agent@… » comptent pour un.
        if (!$this->limiteurEmail->create(mb_strtolower(trim((string) $data->email)))->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: $refus);
        }

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

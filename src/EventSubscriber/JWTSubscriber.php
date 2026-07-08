<?php

namespace App\EventSubscriber;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class JWTSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserRepository $userRepository
    )
    {
    }

    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $user = $event->getToken()->getUser();
        if(!$user instanceof User) {
            return;
        }
        /**
         * @var User
         */
        $fresh = $this->userRepository->find($user->getId()); /*
            - On '$fresh' vu que '$user' est désérialisé depuis le 'jwt' via le payload du token et n'est pas rechargé depuis la base de données, donc..
                - 'jwt': créé le 01-01 → statut = ACTIF
                - 'admin' suspend le '*user' le 05-01 en base de données
                - '$user' utilise son token le 10/01, le $user->getStatut() = ACTIF
        */
        if(!$fresh) {
            return;
        }

        if($fresh->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new AccessDeniedHttpException('Votre compte a été suspendu. Contactez l\'administrateur.');
        }

        if($fresh->getEntreprise() && $fresh->getEntreprise()->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new AccessDeniedHttpException('Votre entreprise a été désactivée. Contactez le support.');
        }

        if($fresh->getGare() && $fresh->getGare()->getStatut() === ReferenceStatus::SUSPENDU->value) {
            throw new AccessDeniedHttpException('Votre gare a été suspendue. Contactez l\'administrateur.');
        }
    }
    /*
        public function onLexikJwtAuthenticationOnJwtCreated($event): void
        {
            $data = $event->getData();
            /**
             * @var User
             *
            $user = $event->getUser();

            if(!$user instanceof User) {
                $event->setData($data);
            }
            /*
                $data['id'] = $user->getId();
                if($user->getEntreprise()) {
                    $data['entrepriseId'] = $user->getEntreprise()->getId();
                }
            *
            $event->setData($data);
        }    
    */
    public static function getSubscribedEvents(): array
    {
        return [
            // 'lexik_jwt_authentication.on_jwt_created' => 'onLexikJwtAuthenticationOnJwtCreated',
            Events::JWT_AUTHENTICATED => 'onJwtAuthenticated'
        ];
    }
}

<?php

namespace App\EventSubscriber;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Un filtre de sécurité qui traduit les exceptions base de données 'Doctrine DBAL' en exceptions HTTP propres, avant le gestionnaire d'erreurs d'ApiPlatform, on ne renvoie jamais de SQL brut au client et ApiPlatform rend ensuite ce HttpException normalement et l'erreur SQL d'origine reste dans la chaîne 'previous' pour les logs
 */
class DatabaseExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0], // 0 > -96 → on passe avant API Platform
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // On remonte la chaîne : l'exception DBAL est souvent encapsulée
        $dbal = null;
        for ($e = $event->getThrowable(); $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof DbalException) {
                $dbal = $e;
                break;
            }
        }
        if ($dbal === null) {
            return; // pas une erreur base de données → comportement par défaut
        }

        $http = match (true) {
            $dbal instanceof UniqueConstraintViolationException => new ConflictHttpException(
                'Un enregistrement avec ces informations existe déjà.',
                $dbal
            ),
            $dbal instanceof ForeignKeyConstraintViolationException => new ConflictHttpException(
                'Cet élément est lié à d\'autres données et ne peut pas être traité.',
                $dbal
            ),
            $dbal instanceof NotNullConstraintViolationException => new UnprocessableEntityHttpException(
                'Un champ obligatoire est manquant.',
                $dbal
            ),
            default => null,
        };

        /*
            // -- Filet plus large (optionnel) : neutraliser TOUTE erreur SQL restante --
            // ex. "Numeric value out of range" (1264). Évite toute fuite de SQL, mais
            // peut masquer une vraie panne serveur (ex. base indisponible) en "données
            // invalides". À activer en connaissance de cause (le détail reste dans les logs).
            if ($http === null) {
                $http = new UnprocessableEntityHttpException(
                    'Une erreur est survenue lors de l\'enregistrement des données.',
                    $dbal
                );
            }
        */

        if ($http === null) {
            return; // autre erreur base (connexion, syntaxe…) → on laisse remonter (500 + log)
        }

        $event->setThrowable($http);
    }
}

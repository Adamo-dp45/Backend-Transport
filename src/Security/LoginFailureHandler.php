<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Logique personnalisée pour gérer l'échec de l'authentification
        // Par exemple, vous pouvez enregistrer l'échec dans un journal ou effectuer d'autres actions
        if($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            return new JsonResponse([
                'code' => Response::HTTP_TOO_MANY_REQUESTS, // 429
                'message' => 'Trop de tentatives de connexion. Réessayez dans quelques minutes.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // comportement habituel pour un mauvais mot de passe / compte suspendu
        return new JsonResponse([
            'code' => Response::HTTP_UNAUTHORIZED, // 401
            'message' => $exception->getMessageKey() // ou un texte fixe « Identifiants invalides. »
        ], Response::HTTP_UNAUTHORIZED);
    }
}


<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route(path: '/api/login_check', name: 'api_login', methods: ['POST'])]
    public function jsonLogin(): JsonResponse
    {
        /**
         * @var User
         */
        $user = $this->getUser();
        return $this->json([
           'username' => $user->getUserIdentifier(),
           'roles' => $user->getRoles()
        ]);
    }
}
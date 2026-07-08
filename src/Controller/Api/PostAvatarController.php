<?php

namespace App\Controller\Api;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class PostAvatarController
{
    public function __construct(
        private Security $security,
        private ValidatorInterface $validator
    )
    {
    }

    public function __invoke(Request $request)
    {
        $file = $request->files->get('file');
        if(!$file) {
            throw new BadRequestHttpException('Une image est requise.');
        }
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $user->setFile($file);
        $errors = $this->validator->validate($user);
        if(count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors); /*
                - On valide l'entité avant de la persistée vu qu'on n'est plus dans le processus de 'ApiPlatform' donc on le fais manuellement
            */
        }
        return $user;
    }
}
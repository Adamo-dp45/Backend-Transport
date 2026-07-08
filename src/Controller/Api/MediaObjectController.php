<?php

namespace App\Controller\Api;

use App\Entity\MediaObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
class MediaObjectController
{
    public function __construct(
        private readonly ValidatorInterface $validator
    )
    {
    }

    public function __invoke(Request $request)
    {
        $file = $request->files->get('file');
        if(!$file) {
            throw new BadRequestHttpException('Une image est requise.');
        }
        $mediaObject = new MediaObject();
        $mediaObject->file = $file;
        $errors = $this->validator->validate($mediaObject);
        if(count($errors) > 0) {
            throw new BadRequestHttpException((string)$errors); /*
                - On le valide manuellement vu qu'on n'est plus dans le processus de 'ApiPlatform'
            */
        }
        return $mediaObject;
    }
}
<?php

namespace App\Controller\Api;

use App\Entity\MediaObject;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Téléversement d'un fichier. `prive=1` le range dans `public/documents`, dossier interdit au serveur
 * web (justificatif) ; sinon c'est une image publique. Voir le docbloc de `MediaObject` pour la raison
 * des deux stockages.
 */
#[AsController]
class MediaObjectController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly Security $security
    )
    {
    }

    public function __invoke(Request $request): MediaObject
    {
        $user = $this->security->getUser();
        if(!$user instanceof User) {
            throw new AccessDeniedException(); // l'entry point 'jwt' en fait un 401, cf. 'MeProvider'
        }

        $file = $request->files->get('file');
        if(!$file) {
            throw new BadRequestHttpException('Un fichier est requis.');
        }

        $prive = $request->request->getBoolean('prive');
        $mediaObject = new MediaObject();
        if($prive) {
            $mediaObject->document = $file;
        } else {
            $mediaObject->file = $file;
        }

        $errors = $this->validator->validate($mediaObject, null, [$prive ? 'prive' : 'public']);
        if(count($errors) > 0) {
            throw new BadRequestHttpException((string)$errors); /*
                - On le valide manuellement vu qu'on n'est plus dans le processus de 'ApiPlatform'
                - Chaque stockage a SON groupe, donc SA liste de formats : un PDF est refusé en public
            */
        }

        /*
            L'ENTREPRISE de celui qui téléverse : c'est elle qui borne ensuite la résolution de l'IRI
            ('EntrepriseScopeExtension'). Nulle pour le super admin, qui n'en a pas — son média reste
            alors invisible de toute compagnie, ce qui est le sens sûr.
            L'AUTEUR : seul lui pourra accrocher un document privé à une dépense ('DepenseProcessor').
        */
        $mediaObject
            ->setIdentreprise($user->getEntreprise()?->getId())
            ->setCreatedBy($user->getId());

        return $mediaObject;
    }
}

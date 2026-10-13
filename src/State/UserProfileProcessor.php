<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class UserProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var User $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        /*
            !! L'E-MAIL N'EST PAS RECOPIÉ, et c'est la seconde moitié de la garde posée sur
            'User::$email' (qui n'est plus dans le groupe 'write:User:profil').

            Retirer le groupe seul ne suffisait PAS — pire, cela cassait le profil : ce processor
            écrivait 'setEmail($data->getEmail())', et comme le champ n'est plus dénormalisé,
            '$data' le rend NULL. L'e-mail de l'agent était donc VIDÉ à chaque enregistrement de
            profil, et la validation répondait 422 « email : cette valeur ne doit pas être vide ».
            Mesuré avant correction.

            La leçon vaut pour tout champ qu'on retire d'un groupe d'écriture : vérifier qu'aucun
            processor ne le recopie depuis l'objet dénormalisé, sinon on ne ferme pas l'écriture —
            on l'écrase avec du vide.

            L'e-mail reste modifiable par l'ADMINISTRATEUR via '/api/users/{id}' (groupe
            'write:User'), qui ne passe pas par ici.
        */
        $user
            ->setNom($data->getNom())
            ->setPrenom($data->getPrenom())
        ;
        return $this->processor->process($user, $operation, $uriVariables, $context);
    }
}

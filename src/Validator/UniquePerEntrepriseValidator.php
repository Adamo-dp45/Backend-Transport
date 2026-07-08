<?php

namespace App\Validator;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class UniquePerEntrepriseValidator extends ConstraintValidator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    )
    {
    }

    public function validate(mixed $entity, Constraint $constraint): void
    {
        if(!$constraint instanceof \App\Validator\UniquePerEntreprise || !$entity) {
            return;
        }

        /**
         * @var User
         */
        $user = $this->security->getUser();
        if(!$user || !method_exists($user, 'getEntreprise')) {
            return;
        }

        $entrepriseId = $user->getEntreprise()->getId();
        $criteria = [
            'identreprise' => $entrepriseId
        ];

        foreach ($constraint->fields as $field) {
            $getter = 'get' . ucfirst($field);
            if(!method_exists($entity, $getter)) {
                return; // Si le champ n'existe pas on stoppe
            }

            $value = $entity->$getter();

            if($value === null || $value === '') {
                return; // Si le champ est vide pas de validation
            }
            /*
                if(is_object($value) && method_exists($value, 'getId')) {
                    $value = $value->getId();
                } -- Pour gérer les relation 'Doctrine' mais déjà gérer par symfony
            */
            $criteria[$field] = $value;
        }

        $metadata = $this->entityManager->getClassMetadata($entity::class);
        if($metadata->hasField('deletedAt')) {
            $criteria['deletedAt'] = null;
        }

        $repository = $this->entityManager->getRepository($entity::class);
        $existing = $repository->findOneBy($criteria);

        if($existing) {
            if(method_exists($entity, 'getId') && $entity->getId() && $existing->getId() === $entity->getId()) {
                return; /*
                    - On exlcus l'entité courante dans le cas de 'edit' et on évite les return dangereux
                */
            }

            $this->context
                ->buildViolation($constraint->message)
                ->atPath($constraint->fields[0])
                ->addViolation()
            ;
        }
    }
}

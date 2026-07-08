<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Data\CorbeilleRegistry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CorbeilleDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private CorbeilleRegistry $registry,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        $type = $uriVariables['type'] ?? null;
        $id   = $uriVariables['id']   ?? null;

        $class = $this->registry->getClass($type);
        if (!$class) {
            throw new BadRequestHttpException('Type invalide : ' . $type);
        }

        $entity = $this->em->getRepository($class)->findOneBy([
            'id'           => $id,
            'identreprise' => $identreprise,
        ]);

        if (!$entity || $entity->getDeletedAt() === null) {
            throw new NotFoundHttpException('Élément introuvable en corbeille');
        }

        $this->em->remove($entity);
        $this->em->flush();

        return null;
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

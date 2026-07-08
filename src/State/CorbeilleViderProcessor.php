<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Data\CorbeilleRegistry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CorbeilleViderProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private CorbeilleRegistry $registry,
        private EntityManagerInterface $em,
        private RequestStack $requestStack
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
        $request = $this->requestStack->getCurrentRequest();
        $types = $request->query->all('type');
        if(empty($types)) {
            $types = $this->registry->getTypes();
        }
        $count = 0;
        foreach($types as $type) {
            $class = $this->registry->getClass($type);
            if(!$class) {
                continue;
            }
            $entities = $this->em->getRepository($class)->findBy([
                'identreprise' => $identreprise,
            ]);
            foreach($entities as $entity) {
                if($entity->getDeletedAt() !== null) {
                    $this->em->remove($entity);
                    $count++;
                }
            }
        }
        $this->em->flush();
        return ['message' => $count . ' élément(s) supprimé(s) définitivement'];
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

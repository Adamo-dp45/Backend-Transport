<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Data\CorbeilleRegistry;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class CorbeilleProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private RequestStack $requestStack,
        private EntityManagerInterface $em,
        private CorbeilleRegistry $registry
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();
        $request = $this->requestStack->getCurrentRequest();
        $typeParam = $request->query->all()['type'] ?? []; /*
            - Les types au format array '?type[]=car[]=courrier' ou string '?type=ticket'
        */
        $types = is_array($typeParam) ? $typeParam : [$typeParam];
        if(empty($types)) {
            $types = $this->registry->getTypes();
        }
        $resultats = [];
        foreach($types as $type) {
            $class = $this->registry->getClass($type);
            if(!$class) {
                continue;
            }
            $query = $this->em->getRepository($class)
                ->createQueryBuilder('e')
                ->where('e.deletedAt IS NOT NULL')
                ->andWhere('e.identreprise = :identreprise')
                ->setParameter('identreprise', $identreprise)
                ->orderBy('e.deletedAt', 'DESC')
            ;
            $items = $query->getQuery()->getResult();
            foreach($items as $item) {
                $resultats[] = [
                    'type' => $type,
                    'id' => $item->getId(),
                    'deletedAt' => $item->getDeletedAt()?->format('Y-m-d H:i'),
                    'label' => $this->getLabel($item, $type)
                ];
            }
        }

        usort($resultats, fn($a, $b) => $b['deletedAt'] <=> $a['deletedAt']); /*
            - On trie par 'deletedAt' desc
        */
        return $resultats;
    }

    private function getLabel(object $entity, string $type): string
    {
        return match(true) {
            method_exists($entity, 'getCodeticket') => $entity->getCodeticket(),
            method_exists($entity, 'getCodecourrier') => $entity->getCodecourrier(),
            method_exists($entity, 'getCodebagage') => $entity->getCodebagage(),
            method_exists($entity, 'getCodevoyage') => $entity->getCodevoyage(),
            method_exists($entity, 'getMatricule') => $entity->getMatricule(),
            method_exists($entity, 'getLibelle') => $entity->getLibelle(),
            method_exists($entity, 'getNom') => $entity->getNom(),
            method_exists($entity, 'getDateappro') => $entity->getDateappro()?->format('d/m/Y'),
            default => $type . ' #' . $entity->getId()
        };
    }
}

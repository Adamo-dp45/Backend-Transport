<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tarif;
use App\Entity\User;
use App\Repository\TarifRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TarifProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private TarifRepository $tarifRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Tarif $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if ($data->getGaredepart() && $data->getGarearrivee()
            && $data->getGaredepart()->getId() === $data->getGarearrivee()->getId()) {
            throw new BadRequestHttpException('La gare de départ et la gare d\'arrivée doivent être différentes');
        }

        $data
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId());

        // Création automatique du tarif inverse (garearrivee → garedepart) au même montant,
        // s'il n'existe pas déjà, pour éviter une double saisie.
        $depart = $data->getGaredepart();
        $arrivee = $data->getGarearrivee();
        if ($depart && $arrivee && $data->getMontant() !== null) {
            $inverseExistant = $this->tarifRepository->findMontant($arrivee->getId(), $depart->getId(), $entrepriseId);
            if (!$inverseExistant) {
                $inverse = (new Tarif())
                    ->setGaredepart($arrivee)
                    ->setGarearrivee($depart)
                    ->setMontant($data->getMontant())
                    ->setIdentreprise($entrepriseId)
                    ->setCreatedBy($user->getId());
                $this->em->persist($inverse); // sera enregistré par le flush du persist_processor
            }
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

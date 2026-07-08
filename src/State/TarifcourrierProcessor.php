<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tarifcourrier;
use App\Entity\User;
use App\Repository\TarifcourrierRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TarifcourrierProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private TarifcourrierRepository $tarifcourrierRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Tarifcourrier $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $data
                ->setIdentreprise($identreprise)
                ->setCreatedBy($user->getId())
            ;
        }
        $this->validerTranche($data, $identreprise);
        $data->setUpdatedAt(new \DateTimeImmutable());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function validerTranche(Tarifcourrier $data, int $identreprise): void
    {
        $valeurMin = (int)$data->getValeurmin();
        $valeurMax = $data->getValeurmax() !== null ? (int) $data->getValeurmax() : null;

        if($valeurMax !== null && $valeurMax <= $valeurMin) {
            throw new BadRequestHttpException('La valeur maximale doit être supérieure à la valeur minimale');
        }

        if($valeurMax === null) { /*
            - On vérifie s'il n'existe pas déjà une tranche illimitée 'valeurmax null' si on essaie d'en créer une nouvelle
        */
            $exist = $this->tarifcourrierRepository->findTrancheIllimitee(
                $identreprise,
                $data->getId() /*
                    - Le 'Null' en 'post' et 'id' en 'patch' pour s'exclure soi-même
                */
            );
            if($exist) {
                throw new BadRequestHttpException('Une tranche illimitée (sans valeur max) existe déjà : "' . $exist->getLibelle() . '"');
            }
        }

        $chevauchement = $this->tarifcourrierRepository->findChevauchement( /*
            - On vérifie le chevauchement avec les tranches existantes
        */
            $valeurMin,
            $valeurMax,
            $identreprise,
            $data->getId()
        );
        if($chevauchement) {
            throw new BadRequestHttpException('Cette tranche chevauche une tranche existante : "' . $chevauchement->getLibelle() . '"');
        }
    }
}

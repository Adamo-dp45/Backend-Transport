<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ReservationConfigService;
use App\Entity\Dto\ParametreReservationInput;
use App\Entity\ParametreReservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Met à jour les paramètres de réservation de l'entreprise courante (singleton auto-créé).
 */
class MeParametreReservationProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ReservationConfigService $config,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ParametreReservation
    {
        /** @var ParametreReservationInput $data */
        /** @var User $user */
        $user = $this->security->getUser();

        // Un pourcentage de pénalité ne peut pas dépasser 100 %
        $valeur = (int) $data->penaliteValeur;
        if ($data->penaliteType === 'POURCENTAGE' && $valeur > 100) {
            throw new BadRequestHttpException('Une pénalité en pourcentage ne peut pas dépasser 100 %');
        }

        $parametre = $this->config->getParametre($user->getEntreprise()->getId());
        $parametre
            ->setDelaiPresentationMinutes((int) $data->delaiPresentationMinutes)
            ->setDelaiPaiementMinutes((int) $data->delaiPaiementMinutes)
            ->setPenaliteType($data->penaliteType ?? 'AUCUNE')
            ->setPenaliteValeur($valeur)
            ->setFenetreRegularisationJours((int) $data->fenetreRegularisationJours)
            ->setUpdatedBy($user->getId());

        $this->em->flush();

        return $parametre;
    }
}

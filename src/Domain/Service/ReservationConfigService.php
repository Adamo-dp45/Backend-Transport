<?php

namespace App\Domain\Service;

use App\Entity\ParametreReservation;
use App\Repository\ParametreReservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accès aux paramètres de réservation d'une entreprise (auto-créés avec les défauts si absents).
 */
class ReservationConfigService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParametreReservationRepository $repository
    )
    {
    }

    public function getParametre(int $identreprise): ParametreReservation
    {
        $parametre = $this->repository->findOneByEntreprise($identreprise);
        if ($parametre === null) {
            $parametre = (new ParametreReservation())->setIdentreprise($identreprise);
            $this->em->persist($parametre);
            $this->em->flush();
        }

        return $parametre;
    }
}

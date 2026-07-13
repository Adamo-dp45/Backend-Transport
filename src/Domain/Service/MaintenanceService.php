<?php

namespace App\Domain\Service;

use App\Entity\Maintenance;
use App\Repository\MaintenanceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * État du mode maintenance global (singleton auto-créé, désactivé par défaut).
 */
class MaintenanceService
{
    public function __construct(
        private MaintenanceRepository $repository,
        private EntityManagerInterface $em
    )
    {
    }

    public function get(): Maintenance
    {
        $maintenance = $this->repository->getSingleton();
        if ($maintenance === null) {
            $maintenance = new Maintenance();
            $this->em->persist($maintenance);
            $this->em->flush();
        }

        return $maintenance;
    }

    public function estActif(): bool
    {
        return $this->get()->isActif();
    }
}

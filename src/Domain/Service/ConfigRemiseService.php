<?php

namespace App\Domain\Service;

use App\Entity\ConfigRemise;
use App\Repository\ConfigRemiseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accès aux paramètres de remise d'une entreprise (singleton auto-créé avec les défauts si absent).
 */
class ConfigRemiseService
{
    public function __construct(
        private ConfigRemiseRepository $repository,
        private EntityManagerInterface $em
    )
    {
    }

    public function get(int $identreprise): ConfigRemise
    {
        $config = $this->repository->findOneByEntreprise($identreprise);
        if ($config === null) {
            $config = (new ConfigRemise())->setIdentreprise($identreprise);
            $this->em->persist($config);
            $this->em->flush();
        }

        return $config;
    }
}

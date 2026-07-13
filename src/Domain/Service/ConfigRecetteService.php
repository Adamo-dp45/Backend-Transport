<?php

namespace App\Domain\Service;

use App\Entity\ConfigRecette;
use App\Repository\ConfigRecetteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accès aux paramètres de recette d'une entreprise (singleton auto-créé avec les défauts si absent).
 */
class ConfigRecetteService
{
    public function __construct(
        private ConfigRecetteRepository $repository,
        private EntityManagerInterface $em
    )
    {
    }

    public function get(int $identreprise): ConfigRecette
    {
        $config = $this->repository->findOneByEntreprise($identreprise);
        if ($config === null) {
            $config = (new ConfigRecette())->setIdentreprise($identreprise);
            $this->em->persist($config);
            $this->em->flush();
        }

        return $config;
    }

    /** Raccourci : les courriers sont-ils exclus du chiffre d'affaires de cette entreprise ? */
    public function courriersHorsCa(int $identreprise): bool
    {
        return $this->get($identreprise)->isCourriershorsca();
    }
}

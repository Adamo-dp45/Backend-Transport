<?php

namespace App\Domain\Service;

use Symfony\Component\Finder\Finder;

class EntityDiscoveryService
{
    public function __construct(
        private string $entityPath
    )
    {
    }

    /**
     * Permet de cacher les entités sur lesquelles on ne peut pas définir des permissions
     * @return array
     */
    public function getEntityList(): array
    {
        $entities = [];
        $finder = new Finder();
        $finder->files()->in($this->entityPath)->name('*.php')->depth(0); /*
            - 'depth' pour n'explorer que la racine du dossier
        */
        foreach($finder as $file) {
            $entityName = $file->getBasename('.php');
            if (!in_array($entityName, [
                'UserRole',
                'Entreprise',
                'EntityBase',
                'Permission',
                'Detailapprovisionnement',
                'Detaildepannage',
                'Detailpersonnel',
                'MediaObject',
                'RefreshToken',
                'Siege',
                'Detailcourrier',
                'PasswordResetToken',
                'Beneficiaire',
                'ProgrammeFidelite',
                'ParametreReservation',
                'Arret'
            ])) {
                $entities[] = $entityName;
            }
        }
        sort($entities);
        return $entities;
    }
}
<?php

namespace App\Entity\Output\Corbeille;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Un enregistrement présent en corbeille (soft-deleté), toutes entreprises confondues. Vue super
 * admin : on expose l'entreprise propriétaire et l'auteur de la suppression pour un pilotage global.
 */
final class CorbeilleItemDto
{
    public function __construct(
        /** Type technique (nom court minuscule, ex. « ticket ») — sert aux URLs d'action. */
        #[Groups(['read:Corbeille'])]
        public readonly string $type,
        #[Groups(['read:Corbeille'])]
        public readonly string $typeLibelle,
        #[Groups(['read:Corbeille'])]
        public readonly int $id,
        /** Libellé humain de l'élément (code billet, matricule, libellé…). */
        #[Groups(['read:Corbeille'])]
        public readonly string $libelle,
        #[Groups(['read:Corbeille'])]
        public readonly ?int $identreprise,
        #[Groups(['read:Corbeille'])]
        public readonly ?string $entreprise,
        #[Groups(['read:Corbeille'])]
        public readonly ?string $deletedAt,
        /** Nom de l'agent qui a supprimé, si connu. */
        #[Groups(['read:Corbeille'])]
        public readonly ?string $deletedBy,
    ) {
    }
}

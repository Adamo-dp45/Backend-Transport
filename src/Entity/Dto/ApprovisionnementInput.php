<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Les contraintes « OBLIGATOIRE » vivent dans le groupe de validation `creation`, que seul le POST
 * active (`validationContext` sur l'opération). Ce qui reste dans `Default` s'applique aux DEUX : une
 * valeur FOURNIE doit rester valide, sans être exigée.
 *
 * POURQUOI : ces champs étaient déclarés non nuls, si bien qu'un PATCH PARTIEL était refusé en 422 par
 * la validation — alors que le processeur était écrit pour le supporter. Le frontend renvoyant toujours
 * la charge utile complète, personne n'avait jamais emprunté le chemin cassé. Verrouillé par
 * `tests/Api/ModificationPartielleTest.php`.
 */
class ApprovisionnementInput
{
    #[Assert\NotNull(groups: ['creation'])]
    #[Groups(['write:ApprovisionnementInput'])]
    public ?int $fournisseur = null;

    /**
     * Les lignes de commande — `Detailapprovisionnement`.
     *
     * `Count(min: 1)` dans `Default` : `null` le traverse, un tableau VIDE est refusé.
     *
     * !! LE 'Count' NE SUFFIT PAS : la contrainte IGNORE null, elle refuse donc '[]' mais laisse
     * passer l'ABSENCE de la clé — et le processeur partait alors en erreur 500. D'où le 'NotNull'
     * dans le groupe 'creation' à côté : les deux contraintes se complètent, aucune ne remplace
     * l'autre.
     */
    #[Assert\NotNull(groups: ['creation'])]
    #[Assert\Count(min: 1)]
    #[Groups(['write:ApprovisionnementInput'])]
    public ?array $details = null;
}
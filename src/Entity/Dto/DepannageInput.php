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
class DepannageInput
{
    /*
        ABSENT veut dire « je n'y touche pas », VIDE veut dire « efface-le » — et effacer le lieu d'un
        dépannage n'a pas de sens. D'où `NotBlank(allowNull: true)` dans `Default` : il laisse passer
        l'absence et refuse la chaîne vide, à la création comme à la modification.
    */
    #[Assert\NotNull(groups: ['creation'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\Length(min: 2)]
    #[Groups(['write:DepannageInput'])]
    public ?string $lieudepannage = null;

    #[Groups(['write:DepannageInput'])]
    public ?string $description = null;

    #[Assert\NotNull(groups: ['creation'])]
    #[Groups(['write:DepannageInput'])]
    public ?int $car = null;

    #[Assert\NotNull(groups: ['creation'])]
    #[Groups(['write:DepannageInput'])]
    public ?int $typepanne = null;

    /**
     * Les pièces consommées — `Detaildepannage`.
     *
     * `Count(min: 1)` reste dans `Default` : `null` le traverse (« je n'y touche pas »), mais un tableau
     * VIDE est refusé. Le processeur ignorait silencieusement `[]`, ce qui laissait croire à une
     * suppression qui n'avait pas lieu.
     *
     * !! LE 'Count' NE SUFFIT PAS : la contrainte IGNORE null, elle refuse donc '[]' mais laisse
     * passer l'ABSENCE de la clé — et le processeur partait alors en erreur 500. D'où le 'NotNull'
     * dans le groupe 'creation' à côté : les deux contraintes se complètent, aucune ne remplace
     * l'autre.
     */
    #[Assert\NotNull(groups: ['creation'])]
    #[Assert\Count(min: 1)]
    #[Groups(['write:DepannageInput'])]
    public ?array $details = null;

    /**
     * MAIN D'ŒUVRE EXTERNE — `Detailmaindoeuvre`. Chaque ligne : `intervenant`, `montant`, et
     * `prestation` en option.
     *
     * NULL N'EST PAS LE TABLEAU VIDE, et la distinction porte du sens sur un PATCH : `null` veut dire
     * « je ne touche pas à la main d'œuvre » (ses lignes et son montant sont conservés), `[]` veut dire
     * « supprime-la ». Sans cette nuance, corriger une pièce effacerait la main d'œuvre du dépannage.
     */
    #[Assert\Valid]
    #[Groups(['write:DepannageInput'])]
    public ?array $maindoeuvres = null;

    // 'Detailpersonnel' géré dans l'affectation
}
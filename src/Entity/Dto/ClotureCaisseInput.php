<?php

namespace App\Entity\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entrée d'une clôture de caisse : ce que l'agent a COMPTÉ, et pourquoi ça ne tombe pas juste.
 *
 * Le théorique n'est PAS demandé — il est calculé par le serveur ('CaisseTheoriqueService'). Le
 * laisser entrer reviendrait à laisser l'appareil dicter ce qu'il aurait dû encaisser, exactement
 * ce que la vente hors ligne du commercial refuse déjà pour les prix.
 */
class ClotureCaisseInput
{
    /**
     * Le total des espèces comptées dans le tiroir, fonds d'ouverture compris.
     *
     * 'PositiveOrZero' et non 'Positive' : un tiroir VIDE est un comptage parfaitement légitime —
     * une caisse ouverte automatiquement, sans fonds, dont les ventes ont toutes été remboursées.
     * Exiger un montant strictement positif obligerait l'agent à mentir pour pouvoir fermer.
     */
    #[Groups(['write:ClotureCaisseInput'])]
    #[Assert\NotNull(message: 'Le montant compté est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le montant compté ne peut pas être négatif')]
    public ?int $montantcompte = null;

    /**
     * Obligatoire dès que l'écart n'est pas nul — la garde est dans le processor, qui est le seul
     * à connaître le théorique, donc l'écart. Une contrainte de validation ne saurait pas le faire.
     */
    #[Groups(['write:ClotureCaisseInput'])]
    #[Assert\Length(max: 255, maxMessage: 'Le motif ne doit pas dépasser {{ limit }} caractères')]
    public ?string $motifecart = null;
}

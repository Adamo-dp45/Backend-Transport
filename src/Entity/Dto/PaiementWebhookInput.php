<?php

namespace App\Entity\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps du webhook de paiement (appelé par le prestataire, ou par le front en mode simulé).
 * Format générique pour l'instant — à adapter au format exact du prestataire (signature, champs).
 */
final class PaiementWebhookInput
{
    #[Assert\NotBlank]
    public ?string $reference = null;

    /** Statut renvoyé par le prestataire (SUCCESS, PAID, FAILED…). */
    public ?string $status = null;
}

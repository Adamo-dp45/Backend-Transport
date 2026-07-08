<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ReservationConfirmationService;
use App\Entity\Dto\PaiementWebhookInput;

/**
 * Webhook de paiement (appelé serveur-à-serveur par le prestataire, ou par le front en simulation).
 * Confirme la réservation par sa référence si le statut indique un succès. Idempotent ; ne rend rien
 * (output false) → 2xx systématique pour que le prestataire ne rejoue pas la notification.
 *
 * ⚠️ À sécuriser au branchement réel : vérifier la SIGNATURE du prestataire (clé/HMAC) avant de confirmer.
 */
final class PaiementWebhookProcessor implements ProcessorInterface
{
    private const STATUTS_SUCCES = ['SUCCESS', 'SUCCES', 'PAID', 'PAYE', 'ACCEPTED', 'COMPLETED', 'OK'];

    public function __construct(
        private ReservationConfirmationService $confirmation
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        /** @var PaiementWebhookInput $data */
        $reference = trim((string) $data->reference);
        $succes = in_array(strtoupper((string) $data->status), self::STATUTS_SUCCES, true);

        if ($reference !== '' && $succes) {
            $this->confirmation->confirmerParReference($reference);
        }

        return null;
    }
}

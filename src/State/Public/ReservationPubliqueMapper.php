<?php

namespace App\State\Public;

use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Output\Reservation\PaiementInfoDto;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Entity\Reservation;

/** Convertit une Reservation en DTO public (données publiques uniquement). Partagé création/suivi. */
final class ReservationPubliqueMapper
{
    public function __construct(private ReservationEcheanceService $echeance)
    {
    }

    public function versDto(Reservation $r, ?PaiementInfoDto $paiement = null): ReservationPubliqueDto
    {
        $voyage = $r->getVoyage();

        return new ReservationPubliqueDto(
            code: (string) $r->getCode(),
            statut: $r->getStatut(),
            etatpaiement: $r->getEtatpaiement(),
            nomclient: $r->getNomclient(),
            contactclient: $r->getContactclient(),
            montant: $r->getPrix(),
            dateexpiration: $r->getDateexpiration()?->format(\DateTimeInterface::ATOM),
            montee: $r->getGare()?->getLibelle(),
            descente: $r->getGaredescente()?->getLibelle(),
            codevoyage: $voyage?->getCodevoyage(),
            // Heure à laquelle le car passe chez CE client, pas celle du départ du voyage.
            heurepassage: $voyage === null
                ? null
                : $this->echeance->heurePassage($voyage, $r->getGare())?->format(\DateTimeInterface::ATOM),
            datedepartprevue: $voyage?->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
            bonDisponible: $r->getEtatpaiement() === 'PAYE',
            billetEmis: $r->getTicket()?->getCodeticket(),
            paiement: $paiement
        );
    }
}

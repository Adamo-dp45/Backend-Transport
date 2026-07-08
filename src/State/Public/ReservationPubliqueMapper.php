<?php

namespace App\State\Public;

use App\Entity\Output\Reservation\PaiementInfoDto;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Entity\Reservation;

/** Convertit une Reservation en DTO public (données publiques uniquement). Partagé création/suivi. */
final class ReservationPubliqueMapper
{
    public function versDto(Reservation $r, ?PaiementInfoDto $paiement = null): ReservationPubliqueDto
    {
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
            codevoyage: $r->getVoyage()?->getCodevoyage(),
            datedepartprevue: $r->getVoyage()?->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
            bonDisponible: $r->getEtatpaiement() === 'PAYE',
            billetEmis: $r->getTicket()?->getCodeticket(),
            paiement: $paiement
        );
    }
}

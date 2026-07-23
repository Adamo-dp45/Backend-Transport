<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Repository\TarifRepository;
use App\Security\VoyageGuard;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Calcul de la RÉGULARISATION d'une réservation payée en no-show (statut A_REGULARISER) :
 * report sur un nouveau départ desservant le même tronçon, avec pénalité (politique compagnie) et
 * complément tarifaire si le tarif du jour est plus élevé (jamais de remboursement si moins cher).
 *
 * Ne persiste rien : renvoie le décompte à encaisser. La bascule effective (siège + billet) est
 * réalisée par {@see App\State\RegulariserReservationProcessor} sous verrou.
 */
class ReservationRegularisationService
{
    public function __construct(
        private TarifRepository $tarifRepository,
        private ReservationConfigService $config,
        private ReservationEcheanceService $echeance,
        private VoyageGuard $voyageGuard
    )
    {
    }

    /**
     * @return array{penalite:int, complement:int, prixInitial:int, nouveauPrix:int, total:int, exoneree:bool}
     */
    public function calculer(Reservation $reservation, Voyage $cible): array
    {
        $entrepriseId = (int) $reservation->getIdentreprise();

        if ($reservation->getStatut() !== ReservationStatus::STATUT_A_REGULARISER->value) {
            throw new BadRequestHttpException('Seule une réservation à régulariser (no-show payé) peut être reportée');
        }
        if ($reservation->getTicket() !== null) {
            throw new BadRequestHttpException('Cette réservation a déjà un billet émis');
        }
        if ((int) $cible->getIdentreprise() !== $entrepriseId) {
            throw new BadRequestHttpException('Départ cible invalide');
        }
        if ($cible->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le départ cible est clôturé');
        }

        $ligne = $cible->getLigne();
        if (!$ligne || $cible->getDatedepartprevue() === null) {
            throw new BadRequestHttpException('Le départ cible n\'a ni ligne ni date de départ');
        }

        // La ligne du départ cible doit desservir le tronçon montée → descente de la réservation
        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $ordreMontee = $ordreParGare[$reservation->getGare()?->getId()] ?? null;
        $ordreDescente = $ordreParGare[$reservation->getGaredescente()?->getId()] ?? null;
        if ($ordreMontee === null || $ordreDescente === null || $ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('Ce départ ne dessert pas le trajet de la réservation (' . ($reservation->getGare()?->getLibelle() ?? '?') . ' → ' . ($reservation->getGaredescente()?->getLibelle() ?? '?') . ')');
        }

        /*
            Le nouveau départ doit encore être réservable POUR CE CLIENT : il doit lui rester le temps
            de se présenter à SA gare de montée, où le car passe plus tard que l'heure de départ du
            voyage. Contrôlé après la validation du tronçon, qui garantit que la montée est bien un
            arrêt de la ligne cible.
        */
        $deadline = $this->echeance->limitePresentationPour($cible, $reservation->getGare(), $entrepriseId);
        if ($deadline === null || $deadline <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Ce départ est trop proche ou déjà passé : choisissez un départ ultérieur');
        }
        /*
            Le car ne doit pas avoir DÉJÀ QUITTÉ la gare de montée du client. L'échéance ci-dessus ne
            le couvre pas : elle se calcule sur l'horaire PRÉVU, qui reste dans le futur pour un car
            parti en avance. Même garde que la création et les deux paiements — la régularisation
            était le dernier chemin par lequel on pouvait encore placer quelqu'un sur un car parti.
        */
        $this->voyageGuard->assertMonteeNonDepassee(
            $cible,
            $reservation->getGare(),
            'Le car de ce départ a déjà quitté ' . ($reservation->getGare()?->getLibelle() ?? 'la gare de montée') . ' : choisissez un autre départ.'
        );

        $prixInitial = (int) $reservation->getPrix();

        // Complément = différence si le tarif ACTUEL du tronçon a augmenté depuis la réservation
        $tarif = $this->tarifRepository->findMontant(
            $reservation->getGare()->getId(),
            $reservation->getGaredescente()->getId(),
            $entrepriseId
        );
        $tarifActuel = $tarif ? (int) $tarif->getMontant() : $prixInitial;
        $complement = max(0, $tarifActuel - $prixInitial);

        /*
            Pas de pénalité si le no-show nous est imputable (départ avancé après le paiement) : la
            réservation porte alors le marqueur posé à la replanification. Le complément tarifaire
            reste dû dans tous les cas — c'est le prix du trajet, pas une sanction.
        */
        $exoneree = $reservation->isPenaliteexoneree();
        $penalite = $exoneree
            ? 0
            : $this->config->getParametre($entrepriseId)->calculerPenalite($prixInitial);

        return [
            'penalite' => $penalite,
            'complement' => $complement,
            'prixInitial' => $prixInitial,
            'nouveauPrix' => $prixInitial + $complement,
            'total' => $penalite + $complement,
            'exoneree' => $exoneree,
        ];
    }
}

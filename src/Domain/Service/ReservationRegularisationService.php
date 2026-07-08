<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReservationStatus;
use App\Entity\Reservation;
use App\Entity\Voyage;
use App\Repository\TarifRepository;
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
        private ReservationConfigService $config
    )
    {
    }

    /**
     * @return array{penalite:int, complement:int, prixInitial:int, nouveauPrix:int, total:int}
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

        // Le nouveau départ doit encore être réservable (départ − délai dans le futur)
        $delaiMinutes = $this->config->getParametre($entrepriseId)->getDelaiExpirationMinutes();
        $deadline = $cible->getDatedepartprevue()->modify('-' . $delaiMinutes . ' minutes');
        if ($deadline <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Ce départ est trop proche ou déjà passé : choisissez un départ ultérieur');
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

        $prixInitial = (int) $reservation->getPrix();

        // Complément = différence si le tarif ACTUEL du tronçon a augmenté depuis la réservation
        $tarif = $this->tarifRepository->findMontant(
            $reservation->getGare()->getId(),
            $reservation->getGaredescente()->getId(),
            $entrepriseId
        );
        $tarifActuel = $tarif ? (int) $tarif->getMontant() : $prixInitial;
        $complement = max(0, $tarifActuel - $prixInitial);

        $penalite = $this->config->getParametre($entrepriseId)->calculerPenalite($prixInitial);

        return [
            'penalite' => $penalite,
            'complement' => $complement,
            'prixInitial' => $prixInitial,
            'nouveauPrix' => $prixInitial + $complement,
            'total' => $penalite + $complement,
        ];
    }
}

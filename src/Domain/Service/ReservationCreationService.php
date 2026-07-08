<?php

namespace App\Domain\Service;

use App\Entity\Gare;
use App\Entity\Reservation;
use App\Repository\TarifRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Création d'une réservation de PLACE, PARTAGÉE entre le guichet (agent, {@see App\State\ReservationProcessor})
 * et l'API publique invité (mobile/web, {@see App\Controller\Public\PublicReservationController}).
 *
 * Valide le tronçon, verrouille le prix (grille tarifaire), fixe l'expiration (départ − délai configurable),
 * vérifie la capacité SOUS VERROU pessimiste, rattache une identité client durable et persiste la
 * réservation EN_ATTENTE. Le siège n'est PAS choisi ici (attribué à l'émission du billet).
 */
class ReservationCreationService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TarifRepository $tarifRepository,
        private ClientResolver $clientResolver,
        private CapaciteService $capaciteService,
        private ReservationConfigService $config
    )
    {
    }

    /**
     * @param Reservation $reservation réservation partiellement remplie (voyage, gare montée, garedescente
     *                                 optionnelle, nomclient, contactclient, source déjà positionnés)
     * @param Gare|null   $userGare    gare de l'agent (null pour MOBILE/invité) — restreint la montée sauf MOBILE
     * @param int|null    $createdBy   id de l'agent (null pour un invité)
     */
    public function creer(Reservation $reservation, int $entrepriseId, ?Gare $userGare, ?int $createdBy): Reservation
    {
        $reservation
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($createdBy);

        $voyage = $reservation->getVoyage();
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage obligatoire');
        }
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé, la réservation est impossible');
        }
        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }
        if ($voyage->getDatedepartprevue() === null) {
            throw new BadRequestHttpException('Ce voyage n\'a pas de date de départ');
        }

        // Fenêtre de réservation : jusqu'à N minutes avant le départ (configurable par entreprise)
        $delaiMinutes = $this->config->getParametre($entrepriseId)->getDelaiExpirationMinutes();
        $expiration = $voyage->getDatedepartprevue()->modify('-' . $delaiMinutes . ' minutes');
        if ($expiration <= new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Trop tard pour réserver ce voyage (le départ est imminent ou passé)');
        }

        // Gares montée / descente (descente par défaut = terminus)
        $garemontee = $reservation->getGare();
        if (!$garemontee) {
            throw new BadRequestHttpException('La gare de montée est obligatoire');
        }
        $garedescente = $reservation->getGaredescente() ?? $ligne->getGareterminus();
        $reservation->setGaredescente($garedescente);

        // Un agent rattaché à une gare ne réserve qu'au départ de SA gare (sauf canal MOBILE)
        if ($reservation->getSource() !== 'MOBILE' && $userGare !== null && $garemontee->getId() !== $userGare->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez réserver qu\'au départ de votre gare (' . $userGare->getLibelle() . ')');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        $monteeId = $garemontee->getId();
        $descenteId = $garedescente->getId();
        if (!isset($ordreParGare[$monteeId], $ordreParGare[$descenteId])) {
            throw new BadRequestHttpException('La gare de montée ou de descente n\'est pas un arrêt de la ligne');
        }
        $ordreMontee = $ordreParGare[$monteeId];
        $ordreDescente = $ordreParGare[$descenteId];
        if ($ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('La gare de descente doit être située après la gare de montée');
        }
        // DÉPART PARTIEL : pas de réservation avant la provenance réelle du voyage (le car n'y passe pas).
        $origineEff = $voyage->getOrigineEffective();
        $ordreProvenance = $origineEff !== null ? ($ordreParGare[$origineEff->getId()] ?? 0) : 0;
        if ($ordreMontee < $ordreProvenance) {
            throw new BadRequestHttpException('Ce voyage part de ' . ($origineEff?->getLibelle() ?? 'sa gare de provenance') . ' : aucune réservation avant cette gare.');
        }

        // Prix verrouillé depuis la grille tarifaire globale
        $tarif = $this->tarifRepository->findMontant($monteeId, $descenteId, $entrepriseId);
        if (!$tarif) {
            throw new BadRequestHttpException('Aucun tarif défini pour ce trajet (' . $garemontee->getLibelle() . ' → ' . $garedescente->getLibelle() . ')');
        }

        // Rattache une identité client durable (find-or-create par téléphone)
        $reservation->setClient(
            $this->clientResolver->resolve($reservation->getNomclient(), $reservation->getContactclient(), $entrepriseId, $createdBy)
        );

        $reservation
            ->setPrix($tarif->getMontant())
            ->setDateexpiration($expiration)
            ->setEtatpaiement('EN_ATTENTE_PAIEMENT');

        // Capacité vérifiée SOUS VERROU sur le voyage (sérialise réservations + ventes concurrentes)
        return $this->em->wrapInTransaction(function () use ($reservation, $voyage, $ordreMontee, $ordreDescente, $entrepriseId) {
            $this->em->lock($voyage, LockMode::PESSIMISTIC_WRITE);

            $this->capaciteService->assertPlaceDisponible($voyage, $ordreMontee, $ordreDescente, $entrepriseId);

            $reservation->setCode($this->generateCode($entrepriseId));
            $this->em->persist($reservation);

            return $reservation;
        });
    }

    private function generateCode(int $entrepriseId): string
    {
        $count = $this->em->getRepository(Reservation::class)->count([
            'identreprise' => $entrepriseId,
        ]);

        return 'RES-' . date('Y') . '-' . ($count + 1);
    }
}

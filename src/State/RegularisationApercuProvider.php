<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationRegularisationService;
use App\Entity\Output\Reservation\RegularisationApercuOutput;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Aperçu du décompte d'une régularisation pour une réservation A_REGULARISER et un départ cible
 * (query ?voyage=) : renvoie possible=false + message si le report n'est pas faisable, sinon les
 * montants (pénalité, complément, total) à encaisser. Ne persiste rien.
 */
class RegularisationApercuProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ReservationRepository $reservationRepository,
        private VoyageRepository $voyageRepository,
        private ReservationRegularisationService $regularisation,
        private RequestStack $requestStack
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): RegularisationApercuOutput
    {
        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        $reservation = $this->reservationRepository->findOneBy([
            'id' => $uriVariables['id'] ?? 0,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$reservation) {
            throw new NotFoundHttpException('Réservation introuvable');
        }

        $voyageId = (int) $this->requestStack->getCurrentRequest()?->query->get('voyage');
        if ($voyageId <= 0) {
            return new RegularisationApercuOutput(possible: false, message: 'Choisissez un départ de report.');
        }

        $cible = $this->voyageRepository->findOneBy(['id' => $voyageId, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
        if (!$cible) {
            return new RegularisationApercuOutput(possible: false, message: 'Départ de report introuvable.');
        }

        try {
            $calcul = $this->regularisation->calculer($reservation, $cible);
        } catch (BadRequestHttpException $e) {
            return new RegularisationApercuOutput(possible: false, message: $e->getMessage());
        }

        // L'agent doit comprendre POURQUOI il n'encaisse pas de pénalité, sinon il croit à un bug.
        $avertissements = [];
        if ($calcul['exoneree']) {
            $avertissements[] = 'Pénalité annulée : le départ initial a été avancé après le paiement, l\'absence n\'est pas imputable au client.';
        }
        if ($cible->getCar() === null) {
            $avertissements[] = 'Aucun car n\'est encore affecté à ce départ : il faudra en affecter un avant d\'émettre le billet.';
        }

        return new RegularisationApercuOutput(
            possible: true,
            message: $avertissements === [] ? null : implode(' ', $avertissements),
            penalite: $calcul['penalite'],
            complement: $calcul['complement'],
            prixInitial: $calcul['prixInitial'],
            nouveauPrix: $calcul['nouveauPrix'],
            total: $calcul['total'],
            exoneree: $calcul['exoneree'],
        );
    }
}

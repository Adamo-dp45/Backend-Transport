<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Enum\ReservationStatus;
use App\Domain\Service\ReservationEcheanceService;
use App\Domain\Service\ReservationRegularisationService;
use App\Entity\Output\Reservation\ReportPossibleDto;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Départs sur lesquels une réservation A_REGULARISER peut être reportée, avec leur décompte.
 *
 * La liste est établie en soumettant chaque départ ouvert au CALCUL DE RÉGULARISATION lui-même :
 * ne survivent que ceux qu'il accepte. Impossible dès lors qu'elle diverge de ce que le report
 * autorisera — c'était tout le problème de la version reconstituée côté guichet, qui filtrait sur
 * la ligne d'origine (or n'importe quelle ligne desservant le tronçon convient) et sur l'heure de
 * départ du voyage (or c'est le passage à la gare du client qui compte).
 */
final class ReportsPossiblesProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ReservationRepository $reservationRepository,
        private VoyageRepository $voyageRepository,
        private ReservationRegularisationService $regularisation,
        private ReservationEcheanceService $echeance
    )
    {
    }

    /** @return ReportPossibleDto[] */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        /*
            Sur une opération de COLLECTION, ApiPlatform exécute le provider AVANT d'évaluer
            l'expression 'security' : sans ce contrôle, une requête anonyme plantait ici (500) au lieu
            du 401 que renvoient les autres routes. On refuse donc explicitement.
        */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }
        $entrepriseId = (int) $user->getEntreprise()?->getId();

        $reservation = $this->reservationRepository->findOneBy([
            'id' => $uriVariables['id'] ?? 0,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$reservation) {
            throw new NotFoundHttpException('Réservation introuvable');
        }
        // Hors de cet état, la notion de report n'a pas de sens : liste vide plutôt qu'une erreur,
        // la page de détail interroge cette route sans savoir si elle s'applique.
        if ($reservation->getStatut() !== ReservationStatus::STATUT_A_REGULARISER->value) {
            return [];
        }

        $voyageActuelId = $reservation->getVoyage()?->getId();

        $reports = [];
        foreach ($this->voyageRepository->findOuvertsPourEntreprise($entrepriseId) as $cible) {
            if ($cible->getId() === $voyageActuelId) {
                continue;
            }
            try {
                // Seul juge : si le calcul passe, le report passera.
                $calcul = $this->regularisation->calculer($reservation, $cible);
            } catch (BadRequestHttpException) {
                continue;
            }

            $reports[] = new ReportPossibleDto(
                voyageId: (int) $cible->getId(),
                codevoyage: $cible->getCodevoyage(),
                numerodepart: $cible->getNumerodepart(),
                provenance: $cible->getProvenance(),
                destination: $cible->getDestination(),
                heurepassage: $this->echeance->heurePassage($cible, $reservation->getGare())?->format(\DateTimeInterface::ATOM),
                datedepartprevue: $cible->getDatedepartprevue()?->format(\DateTimeInterface::ATOM),
                car: $cible->getCar()?->getMatricule(),
                enRoute: $cible->getDatedepartreelle() !== null,
                penalite: $calcul['penalite'],
                complement: $calcul['complement'],
                total: $calcul['total'],
            );
        }

        // Le plus proche d'abord : c'est celui qu'on propose au client au comptoir.
        usort($reports, fn (ReportPossibleDto $a, ReportPossibleDto $b) => ($a->heurepassage ?? '') <=> ($b->heurepassage ?? ''));

        return $reports;
    }
}

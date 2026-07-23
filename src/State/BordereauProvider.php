<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\ReservationEcheanceService;
use App\Entity\Output\Bordereau\BordereauGareDto;
use App\Entity\Output\Bordereau\BordereauOutput;
use App\Entity\Output\Bordereau\BordereauPassagerDto;
use App\Entity\Output\Bordereau\BordereauVoyageDto;
use App\Entity\User;
use App\Entity\Voyage;
use App\Domain\Enum\TicketStatus;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\GareRepository;
use App\Repository\PassageRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BordereauProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly TicketRepository $ticketRepository,
        private readonly VoyageRepository $voyageRepository,
        private readonly GareRepository $gareRepository,
        private readonly BagageRepository $bagageRepository,
        private readonly CourrierRepository $courrierRepository,
        private readonly RequestStack $requestStack,
        private readonly ReservationEcheanceService $echeance,
        private readonly PassageRepository $passageRepository
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        $voyageId = $uriVariables['id'] ?? null;
        $gareId = (int)$this->requestStack->getCurrentRequest()?->query->get('gare');

        if (!$gareId) {
            throw new BadRequestHttpException('Le paramètre gare est obligatoire');
        }

        $voyage = $this->voyageRepository->findOneBy([
            'id' => $voyageId,
            'identreprise' => $identreprise,
            'deletedAt' => null
        ]);
        if(!$voyage) {
            throw new NotFoundHttpException('Voyage introuvable');
        }

        $gare = $this->gareRepository->findOneBy([
            'id' => $gareId,
            'identreprise' => $identreprise,
            'deletedAt' => null
        ]);
        if(!$gare) {
            throw new NotFoundHttpException('Gare introuvable');
        }

        $stats = $this->ticketRepository->findBordereauStats($voyageId, (int)$gareId, $identreprise);
        $rawPassagers = $this->ticketRepository->findPassagers($voyageId, (int)$gareId, $identreprise);

        // Occupation au DÉPART de cette gare : ce que le car emporte quand il sort d'ici
        [$occDepart, $libreDepart] = $this->occupationAuDepart($voyage, (int)$gareId);

        // Cargo déposé à cette gare pour ce voyage (comptes seuls) : aide de réconciliation gare→car
        $nbBagages   = $this->bagageRepository->countByVoyageEtGare((int)$voyageId, (int)$gareId, $identreprise);
        $nbCourriers = $this->courrierRepository->countByVoyageEtGare((int)$voyageId, (int)$gareId, $identreprise);

        // Horaires de passage du car à CETTE gare : PRÉVU (somme des tronçons) vs RÉEL (Passage), retard,
        // temps d'arrêt. Le retard se situe sur l'arrivée réelle, ou sur le départ pour l'origine (qui
        // n'a pas d'arrivée). Négatif = le car était en avance.
        $heurePrevue = $this->echeance->heurePassage($voyage, $gare);
        $passage = $this->passageRepository->findOneParVoyageGare((int) $voyageId, (int) $gareId);
        $arrivee = $passage?->getArriveeReelle();
        $reference = $arrivee ?? $passage?->getDepartReelle();
        $retard = ($heurePrevue !== null && $reference !== null)
            ? (int) round(($reference->getTimestamp() - $heurePrevue->getTimestamp()) / 60)
            : null;

        $passagers = array_map(
            fn($p) => new BordereauPassagerDto(
                codeticket: $p['codeticket'],
                nomclient: $p['nomclient'],
                contactclient: $p['contactclient'],
                prix: (float)$p['prix'],
                siegenumero: (int)$p['siegenumero'],
                createdat: $p['createdat'] instanceof \DateTimeInterface ? $p['createdat']->format('d/m/Y H:i') : $p['createdat']
            ),
            $rawPassagers
        );

        return new BordereauOutput(
            voyage: new BordereauVoyageDto(
                id: $voyage->getId(),
                codevoyage: $voyage->getCodevoyage(),
                provenance: $voyage->getProvenance(),
                destination: $voyage->getDestination(),
                datedepartprevue: $voyage->getDatedepartprevue()?->format('d/m/Y H:i') ?? '',
                placestotal: $voyage->getPlacestotal(),
                placesoccupees: $voyage->getTicketsCount() // billets actifs comptés à la volée (ex-colonne 'placesoccupees')
            ),
            gare: new BordereauGareDto(
                id: $gare->getId(),
                libelle: $gare->getLibelle(),
                ville: $gare->getVille()?->getNom(),
                heurePrevue: $heurePrevue?->format('H:i'),
                arriveeReelle: $arrivee?->format('H:i'),
                departReelle: $passage?->getDepartReelle()?->format('H:i'),
                retardMinutes: $retard,
                tempsArretMinutes: $passage?->getTempsArretMinutes(),
            ),
            nbtickets: $stats['nbtickets'],
            recette: $stats['recette'],
            placesrestantes: $voyage->getPlacestotal() - $voyage->getTicketsCount(),
            generele: (new \DateTime())->format('d/m/Y à H:i'),
            passagers: $passagers,
            placesoccupeesdepart: $occDepart,
            placeslibresdepart: $libreDepart,
            nbbagages: $nbBagages,
            nbcourriers: $nbCourriers
        );
    }

    /**
     * Nombre de sièges OCCUPÉS et LIBRES au départ d'une gare donnée : on compte les billets VALIDE
     * dont le tronçon couvre la sortie de cette gare (monté avant/à cette gare ET descend après).
     * La descente effective tient compte d'un passager descendu en route (garedescentereelle).
     *
     * @return array{0:int,1:int} [occupées, libres]
     */
    private function occupationAuDepart(Voyage $voyage, int $gareId): array
    {
        $placestotal = $voyage->getPlacestotal() ?? 0;
        $ligne = $voyage->getLigne();
        if (!$ligne) {
            return [0, $placestotal];
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }
        if (!isset($ordreParGare[$gareId])) {
            return [0, $placestotal];
        }
        $ordreGare = $ordreParGare[$gareId];
        $ordreTerminus = $ordreParGare[$ligne->getGareterminus()->getId()] ?? PHP_INT_MAX;

        $occupes = 0;
        foreach ($voyage->getTickets() as $t) {
            if ($t->getStatut() !== TicketStatus::STATUT_VALIDE->value || $t->getDeletedAt() !== null) {
                continue;
            }
            $tm = $ordreParGare[$t->getGare()?->getId()] ?? null;
            $eff = $t->getGaredescenteEffective();
            $td = $eff ? ($ordreParGare[$eff->getId()] ?? null) : $ordreTerminus;
            if ($tm === null || $td === null) {
                continue;
            }
            if ($tm <= $ordreGare && $td > $ordreGare) {
                $occupes++;
            }
        }

        return [$occupes, max(0, $placestotal - $occupes)];
    }
}

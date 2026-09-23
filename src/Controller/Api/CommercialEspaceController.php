<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\PassageRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace du COMMERCIAL à bord : ses voyages en cours (où il est le commercial et qui ne sont pas encore
 * clôturés), avec la position courante du car et les arrêts de la ligne — pour qu'il gère sa progression
 * et vende SANS passer par la page d'administration du voyage. Scopé au commercial connecté.
 */
final class CommercialEspaceController extends AbstractController
{
    #[Route('/api/voyages/me/commercial', name: 'api_voyages_me_commercial', methods: ['GET'])]
    public function mesVoyages(
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository,
        BagageRepository $bagageRepository,
        PassageRepository $passageRepository
    ): JsonResponse {
        /** @var User $user */
        $user = $security->getUser();
        $entId = $user->getEntreprise()->getId();

        // Voyages ACTIFS (non clôturés) dont je suis le commercial.
        $voyages = $voyageRepository->findBy([
            'commercial' => $user,
            'identreprise' => $entId,
            'datearriveereelle' => null,
            'deletedAt' => null,
        ], ['datedepartprevue' => 'ASC']);

        // Ma recette PROPRE par voyage : mes billets + mes bagages (canal commercial).
        $mesTickets = [];
        foreach ($ticketRepository->recetteCommercialeParVoyage($user->getId(), $entId) as $r) {
            $mesTickets[(int) $r['voyageid']] = ['nb' => (int) $r['nbtickets'], 'recette' => (int) $r['recette']];
        }
        $mesBagages = [];
        foreach ($bagageRepository->recetteCommercialeParVoyage($user->getId(), $entId) as $r) {
            $mesBagages[(int) $r['voyageid']] = ['nb' => (int) $r['nbbagages'], 'recette' => (int) $r['recette']];
        }

        $data = [];
        foreach ($voyages as $v) {
            $arrets = [];
            $ligne = $v->getLigne();
            if ($ligne) {
                foreach ($ligne->getArrets() as $a) {
                    $arrets[] = [
                        'id' => $a->getGare()->getId(),
                        'libelle' => $a->getGare()->getLibelle(),
                        'ordre' => $a->getOrdre(),
                    ];
                }
                usort($arrets, fn ($x, $y) => $x['ordre'] <=> $y['ordre']);
            }

            // Position courante = garecourante, sinon l'origine effective (gareprovenance pour un départ partiel)
            $courante = $v->getGarecourante() ?? $v->getOrigineEffective();

            // Le car peut-il « repartir » de sa position ? Seulement à une gare INTERMÉDIAIRE (ni origine
            // ni terminus), voyage parti et non clôturé, arrivée déjà marquée et départ pas encore posé.
            $origineEff = $v->getOrigineEffective();
            $terminus = $v->getLigne()?->getGareterminus();
            $peutRepartir = false;
            if ($courante !== null && $origineEff !== null && $terminus !== null
                && $courante->getId() !== $origineEff->getId()
                && $courante->getId() !== $terminus->getId()
                && $v->getDatedepartreelle() !== null && $v->getDatearriveereelle() === null) {
                $passage = $passageRepository->findOneParVoyageGare((int) $v->getId(), (int) $courante->getId());
                $peutRepartir = $passage !== null && $passage->getArriveeReelle() !== null && $passage->getDepartReelle() === null;
            }

            $vid = $v->getId();
            $recetteBillets = $mesTickets[$vid]['recette'] ?? 0;
            $recetteBagages = $mesBagages[$vid]['recette'] ?? 0;

            $data[] = [
                'id' => $vid,
                'codevoyage' => $v->getCodevoyage(),
                'numerodepart' => $v->getNumerodepart(),
                'provenance' => $v->getProvenance(),
                'destination' => $v->getDestination(),
                'datedepartprevue' => $v->getDatedepartprevue()?->format('Y-m-d\TH:i:sP'),
                'demarre' => $v->getDatedepartreelle() !== null,
                // Car affecté : nécessaire au plan de sièges côté app mobile (/api/sieges?car=…).
                'carId' => $v->getCar()?->getId(),
                'carMatricule' => $v->getCar()?->getMatricule(),
                'placestotal' => $v->getPlacestotal() ?? 0,
                'placesoccupees' => $v->getTicketsCount(),
                'garecouranteId' => $courante?->getId(),
                'garecouranteLibelle' => $courante?->getLibelle(),
                'peutRepartir' => $peutRepartir,
                'arrets' => $arrets,
                // Ma performance PROPRE sur ce voyage (motivation du vendeur)
                'maRecette' => $recetteBillets + $recetteBagages,
                'mesTickets' => $mesTickets[$vid]['nb'] ?? 0,
                'mesBagages' => $mesBagages[$vid]['nb'] ?? 0,
            ];
        }

        return $this->json(['voyages' => $data]);
    }
}

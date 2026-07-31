<?php

namespace App\Controller\Api;

use App\Domain\Enum\TicketStatus;
use App\Domain\Service\CapaciteService;
use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace commercial — lecture de ses ventes et du manifeste d'un voyage.
 *
 * Scopé au commercial connecté (identité SERVEUR) ET NON soumis au périmètre gare
 * (GareScopeExtension) : le vendeur À BORD opère depuis des gares qui ne sont pas sa
 * gare d'attache — les collections /api/tickets et /api/bagages, filtrées par la gare
 * de l'agent, masqueraient ses ventes en route. Comme CommercialEspaceController, on
 * passe par des requêtes repo directes (hors pipeline API Platform).
 */
final class CommercialVentesController extends AbstractController
{
    #[Route('/api/voyages/{id}/me/ventes', name: 'api_voyages_me_ventes', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function mesVentes(
        int $id,
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository,
        BagageRepository $bagageRepository
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['message' => 'Non authentifié'], 401);
        }
        $entId = $user->getEntreprise()->getId();

        $voyage = $voyageRepository->findOneBy(['id' => $id, 'identreprise' => $entId]);
        if (!$voyage) {
            return $this->json(['tickets' => [], 'bagages' => []]);
        }

        $tickets = $ticketRepository->findBy([
            'voyage' => $voyage,
            'commercial' => $user,
            'identreprise' => $entId,
            'deletedAt' => null,
        ], ['createdAt' => 'DESC']);

        $bagages = $bagageRepository->findBy([
            'voyage' => $voyage,
            'commercial' => $user,
            'identreprise' => $entId,
            'deletedAt' => null,
        ], ['createdAt' => 'DESC']);

        return $this->json([
            'tickets' => array_map(static fn ($t) => [
                'id' => $t->getId(),
                'codeticket' => $t->getCodeticket(),
                'prix' => $t->getPrix(),
                'remise' => $t->getRemise(),
                'nomclient' => $t->getNomclient(),
                'contactclient' => $t->getContactclient(),
                'statut' => $t->getStatut(),
                'siegeNumero' => $t->getSiege()?->getNumero(),
                'monteeGareId' => $t->getGare()?->getId(),
                'monteeLibelle' => $t->getGare()?->getLibelle(),
                'descenteGareId' => $t->getGaredescente()?->getId(),
                'descenteLibelle' => $t->getGaredescente()?->getLibelle(),
                'dateEmission' => $t->getCreatedAt()?->format('Y-m-d\TH:i:sP'),
            ], $tickets),
            'bagages' => array_map(static fn ($b) => [
                'id' => $b->getId(),
                'codebagage' => $b->getCodebagage(),
                'nature' => $b->getNature(),
                'type' => $b->getType(),
                'poids' => $b->getPoids(),
                'montant' => $b->getMontant(),
                'montantforce' => $b->isMontantforce(),
                'statut' => $b->getStatut(),
                'ticketId' => $b->getTicket()?->getId(),
                // Enrichissements pour le reçu bagage (identité + trajet).
                'codeticket' => $b->getTicket()?->getCodeticket(),
                'nomclient' => $b->getTicket()?->getNomclient(),
                'contactclient' => $b->getTicket()?->getContactclient(),
                'monteeLibelle' => $b->getGaredepart()?->getLibelle(),
                'descenteLibelle' => $b->getGaredescente()?->getLibelle(),
            ], $bagages),
        ]);
    }

    /**
     * Manifeste NOMINATIF des passagers d'un voyage : TOUS les billets VALIDE (tout canal — guichet,
     * commercial, réservation), pour le contrôle à bord. Réservé au commercial DU voyage. Trié par
     * numéro de siège.
     *
     * Distinct de VoyageManifesteController (/api/voyages/{id}/manifeste), qui produit une feuille de
     * route AGRÉGÉE par gare / tronçon, pas la liste des passagers.
     */
    #[Route('/api/voyages/{id}/me/manifeste', name: 'api_voyages_me_manifeste', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function manifeste(
        int $id,
        Security $security,
        VoyageRepository $voyageRepository,
        TicketRepository $ticketRepository,
        CapaciteService $capaciteService
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['message' => 'Non authentifié'], 401);
        }
        $entId = $user->getEntreprise()->getId();

        $voyage = $voyageRepository->findOneBy(['id' => $id, 'identreprise' => $entId]);
        if (!$voyage) {
            return $this->json(['passagers' => []]);
        }
        if ($voyage->getCommercial()?->getId() !== $user->getId()) {
            return new JsonResponse(['message' => 'Vous n\'êtes pas le commercial de ce voyage'], 403);
        }

        $tickets = $ticketRepository->findBy([
            'voyage' => $voyage,
            'identreprise' => $entId,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]);

        // On EXCLUT les billets ÉVINCÉS (siège repris par la priorité amont) : ces passagers ne
        // monteront pas, les annoncer sur le manifeste décrirait un car que personne n'occupe.
        $evinces = $capaciteService->billetsEvinces($voyage, $entId);
        $tickets = array_filter($tickets, static fn ($t) => !isset($evinces[$t->getId()]));

        $passagers = array_map(static fn ($t) => [
            'ticketId' => $t->getId(),
            'codeticket' => $t->getCodeticket(),
            'siegeNumero' => $t->getSiege()?->getNumero(),
            'nomclient' => $t->getNomclient(),
            'contactclient' => $t->getContactclient(),
            'monteeLibelle' => $t->getGare()?->getLibelle(),
            'descenteLibelle' => $t->getGaredescente()?->getLibelle(),
            'aBord' => $t->getCommercial() !== null, // vente à bord (par un commercial) vs guichet/réservation
        ], $tickets);

        usort($passagers, static fn ($a, $b) => ($a['siegeNumero'] ?? 0) <=> ($b['siegeNumero'] ?? 0));

        return $this->json(['passagers' => $passagers]);
    }
}

<?php

namespace App\Controller\Api;

use App\Domain\Service\SynchronisationHorsLigneService;
use App\Entity\User;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Remontée des ventes encaissées HORS LIGNE par le vendeur à bord.
 *
 * Comme {@see CommercialVentesController}, on sort du pipeline API Platform : le vendeur à bord opère
 * depuis des gares qui ne sont pas sa gare d'attache, et le périmètre gare masquerait ses ventes en
 * route. L'identité du commercial vient du SERVEUR (jeton), jamais du corps de la requête.
 *
 * PREMIER POINT D'ENTRÉE DE L'API À ACCEPTER UN LOT. Toutes les autres écritures portent un objet à
 * la fois ; ici le téléphone remonte sa file telle qu'elle s'est constituée. D'où trois partis pris :
 *
 *  - L'ordre d'émission est CONSERVÉ (un bagage suit son billet, une avance de position suit les
 *    ventes du tronçon précédent).
 *  - Le lot n'échoue jamais en bloc : chaque opération rapporte son sort. Un billet refusé ne doit
 *    pas emporter les quinze suivants, qui sont peut-être parfaitement valides.
 *  - Une seule transaction pour tout le lot : soit la file est absorbée, soit rien ne bouge. Le
 *    téléphone peut donc renvoyer sans crainte — l'idempotence par référence fait le reste.
 */
final class CommercialSyncController extends AbstractController
{
    /** Garde-fou : au-delà, c'est un appareil en anomalie, pas une journée de vente. */
    private const MAX_OPERATIONS = 200;

    #[Route(
        '/api/voyages/{id}/me/sync',
        name: 'api_voyages_me_sync',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    public function synchroniser(
        int $id,
        Request $request,
        Security $security,
        VoyageRepository $voyageRepository,
        SynchronisationHorsLigneService $synchronisation,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Non authentifié'], 401);
        }

        $voyage = $voyageRepository->findOneBy([
            'id' => $id,
            'identreprise' => $user->getEntreprise()?->getId(),
            'deletedAt' => null,
        ]);
        if ($voyage === null) {
            return $this->json(['message' => 'Voyage introuvable'], 404);
        }

        /*
            Seul le commercial DU VOYAGE remonte des ventes à bord. Un agent de guichet n'a rien à
            faire ici : ses ventes passent par le pipeline normal, il a du réseau.
        */
        if ($voyage->getCommercial()?->getId() !== $user->getId()) {
            return $this->json(['message' => 'Seul le commercial de ce voyage peut y synchroniser des ventes'], 403);
        }

        /*
            Le voyage CLÔTURÉ ferme la synchronisation : c'est le bornage de la vente hors ligne. Un
            billet resté en file après la clôture ne peut plus être rattaché — il se règle à la gare,
            pas par l'application.
        */
        if ($voyage->getDatearriveereelle() !== null) {
            return $this->json([
                'message' => 'Ce voyage est clôturé : les ventes en attente doivent être régularisées à la gare',
            ], 409);
        }

        $corps = json_decode($request->getContent(), true);
        $operations = is_array($corps) && is_array($corps['operations'] ?? null) ? $corps['operations'] : null;

        if ($operations === null) {
            return $this->json(['message' => 'Corps invalide : un tableau « operations » est attendu'], 400);
        }
        if (count($operations) > self::MAX_OPERATIONS) {
            return $this->json([
                'message' => sprintf('Lot trop volumineux (%d opérations, maximum %d)', count($operations), self::MAX_OPERATIONS),
            ], 413);
        }

        $resultats = $em->wrapInTransaction(
            fn (): array => $synchronisation->rejouer($operations, $voyage, $user)
        );

        return $this->json([
            'resultats' => $resultats,
            'acceptes' => $this->compter($resultats, SynchronisationHorsLigneService::ACCEPTE),
            'dejaSynchronises' => $this->compter($resultats, SynchronisationHorsLigneService::DEJA_SYNCHRONISE),
            'refuses' => $this->compter($resultats, SynchronisationHorsLigneService::REFUSE),
        ]);
    }

    /** @param list<array<string, mixed>> $resultats */
    private function compter(array $resultats, string $statut): int
    {
        return count(array_filter($resultats, static fn (array $r): bool => ($r['statut'] ?? null) === $statut));
    }
}

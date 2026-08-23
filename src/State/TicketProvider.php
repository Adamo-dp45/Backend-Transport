<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\CapaciteService;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lecture d'un billet / d'une liste de billets, enrichie du repère ÉVINCÉ.
 *
 * On se BRANCHE au pipeline natif d'API Platform (on ne le remplace pas) : les filtres, le tri, la
 * pagination et surtout les extensions de périmètre (EntrepriseScopeExtension, GareScopeExtension)
 * s'appliquent normalement. On se contente de poser 'evince' sur les entités renvoyées — le paginator
 * est donc conservé tel quel, sans réemballage (à la différence d'InventaireProvider, qui doit
 * remapper vers un DTO).
 *
 * ÉVINCÉ = à son propre point de montée, le siège du billet est déjà pris par un passager monté plus
 * tôt (priorité amont assumée, cf. CapaciteService::billetsEvinces). L'information est DÉRIVÉE à
 * chaque lecture, jamais stockée : elle s'éteint d'elle-même dès que l'occupant amont se désiste ou
 * descend en route.
 *
 * Coût : un calcul par VOYAGE DISTINCT de la page (mémoïsé ci-dessous), pas un par billet. Un listing
 * filtré sur un voyage — le cas courant, celui du manifeste et de la fiche voyage — ne coûte donc
 * qu'un seul calcul.
 */
class TicketProvider implements ProviderInterface
{
    /** @var array<int, array<int, true>> voyageId => ids des billets évincés (mémoïsation par requête) */
    private array $evincesParVoyage = [];

    public function __construct(
        #[Autowire(service: CollectionProvider::class)]
        private readonly ProviderInterface $collectionProvider,
        #[Autowire(service: ItemProvider::class)]
        private readonly ProviderInterface $itemProvider,
        private readonly CapaciteService $capaciteService,
        private readonly VoyageGuard $voyageGuard,
        private readonly Security $security
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof GetCollection) {
            $data = $this->collectionProvider->provide($operation, $uriVariables, $context);
            foreach ($data as $ticket) {
                if ($ticket instanceof Ticket) {
                    $this->marquer($ticket);
                }
            }

            // Le paginator est renvoyé INTACT : le marquage mute les entités déjà chargées, il n'y a
            // ni transformation ni copie qui obligerait à le reconstruire.
            return $data;
        }

        $data = $this->itemProvider->provide($operation, $uriVariables, $context);
        if ($data instanceof Ticket) {
            $this->marquer($data);
        }

        return $data;
    }

    private function marquer(Ticket $ticket): void
    {
        $voyage = $ticket->getVoyage();

        // Position du car vis-à-vis de la gare de MONTÉE de ce billet : c'est la borne qui ferme le
        // désistement, et la modification pour la GARE. Le front s'en sert pour ne pas proposer une
        // action que le serveur refuserait.
        $depassee = $voyage !== null && $this->voyageGuard->monteeDepassee($voyage, $ticket->getGare());
        $ticket->setMonteedepassee($depassee);
        $ticket->setModifiable($this->correctionOuverte($ticket, $voyage, $depassee));

        $entrepriseId = $ticket->getIdentreprise();
        if ($voyage === null || $voyage->getLigne() === null || $entrepriseId === null) {
            return; // sans ligne, aucun ordre d'arrêt : rien à arbitrer pour l'éviction
        }

        $voyageId = $voyage->getId();
        $this->evincesParVoyage[$voyageId] ??= $this->capaciteService->billetsEvinces($voyage, $entrepriseId);

        // Un billet REPORTE/ANNULE n'est jamais dans la carte (billetsEvinces ne lit que les VALIDE) :
        // il ressort donc à false, ce qui est exact — il ne dispute plus aucun siège.
        $ticket->setEvince(isset($this->evincesParVoyage[$voyageId][$ticket->getId()]));
    }

    /**
     * La correction reste-t-elle ouverte AU LECTEUR ? Reproduit, prédicat pour prédicat, ce
     * qu'applique {@see App\State\TicketUpdateProcessor} — d'où l'appel aux mêmes méthodes de
     * VoyageGuard plutôt qu'à une règle réécrite ici.
     *
     * Le VENDEUR À BORD a une borne à lui, mais SUR SES SEULES VENTES : il vend depuis le car, y
     * compris après le départ, et doit pouvoir se relire tant que le véhicule n'a pas atteint
     * l'escale suivante. Le juger avec la borne de la gare ('monteedepassee') lui masquait
     * « Modifier » sur les billets qu'il venait d'émettre en route, alors que l'API les acceptait.
     *
     * Sur les billets qu'il n'a PAS vendus, il retombe dans le cas commun — c'est-à-dire la borne de
     * la gare : être le commercial du voyage n'ouvre pas les billets émis au guichet.
     *
     * Ne couvre QUE la borne temporelle : l'appartenance du billet (gare émettrice / vendeur) et les
     * permissions RBAC restent arbitrées ailleurs.
     */
    private function correctionOuverte(Ticket $ticket, ?Voyage $voyage, bool $monteeDepassee): bool
    {
        if ($voyage === null) {
            return true; // aucun voyage à opposer : le processor ne bloquerait pas non plus
        }

        if ($voyage->getDatearriveereelle() !== null) {
            return false; // voyage clôturé : fermé à tout le monde, commercial compris
        }

        $user = $this->security->getUser();
        if ($user instanceof User && $ticket->getCommercial()?->getId() === $user->getId()) {
            return $this->voyageGuard->surLaGareDeMontee($voyage, $ticket->getGare());
        }

        return !$monteeDepassee;
    }
}

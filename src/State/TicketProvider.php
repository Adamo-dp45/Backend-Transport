<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Service\CapaciteService;
use App\Entity\Ticket;
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
        private readonly CapaciteService $capaciteService
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
        $entrepriseId = $ticket->getIdentreprise();
        if ($voyage === null || $voyage->getLigne() === null || $entrepriseId === null) {
            return; // sans ligne, aucun ordre d'arrêt : rien à arbitrer
        }

        $voyageId = $voyage->getId();
        $this->evincesParVoyage[$voyageId] ??= $this->capaciteService->billetsEvinces($voyage, $entrepriseId);

        // Un billet REPORTE/ANNULE n'est jamais dans la carte (billetsEvinces ne lit que les VALIDE) :
        // il ressort donc à false, ce qui est exact — il ne dispute plus aucun siège.
        $ticket->setEvince(isset($this->evincesParVoyage[$voyageId][$ticket->getId()]));
    }
}

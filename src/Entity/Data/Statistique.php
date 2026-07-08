<?php

namespace App\Entity\Data;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Output\Agent\AgentStatistiqueOutput;
use App\Entity\Output\Bagage\BagageStatistiqueOutput;
use App\Entity\Output\Billetterie\BilleterieStatistiqueOutput;
use App\Entity\Output\Caisse\CaisseOutput;
use App\Entity\Output\Client\ClientStatistiqueOutput;
use App\Entity\Output\Courrier\CourrierStatistiqueOutput;
use App\Entity\Output\Exploitation\ExploitationStatistiqueOutput;
use App\Entity\Output\Fidelite\FideliteStatistiqueOutput;
use App\Entity\Output\Financier\FinancierStatistiqueOutput;
use App\Entity\Output\Flotte\FlotteStatistiqueOutput;
use App\Entity\Output\FlotteActivity\FlotteActiviteOutput;
use App\Entity\Output\Ligne\LigneStatistiqueOutput;
use App\Entity\Output\Personnel\PersonnelStatistiqueOutput;
use App\Entity\Output\ReservationStats\ReservationStatistiqueOutput;
use App\Entity\Output\Stock\StockStatistiqueOutput;
use App\State\AgentStatsProvider;
use App\State\BagageStatsProvider;
use App\State\BilleterieStatsProvider;
use App\State\CaisseStatsProvider;
use App\State\ClientStatsProvider;
use App\State\CourrierStatsProvider;
use App\State\ExploitationStatsProvider;
use App\State\FideliteStatsProvider;
use App\State\FinancierStatsProvider;
use App\State\FlotteActiviteStatsProvider;
use App\State\FlotteStatsProvider;
use App\State\LigneStatsProvider;
use App\State\PersonnelStatsProvider;
use App\State\ReservationStatsProvider;
use App\State\StockStatsProvider;

#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    operations: [
        new Get( /*
            - Pas de 'GetCollection' sinon 'ApiPlatform' s'attend à une liste d'éléments et transforme le retour en tableau indexé
        */
            uriTemplate: '/stats/exploitation',
            provider: ExploitationStatsProvider::class,
            input: false,
            output: ExploitationStatistiqueOutput::class, /*
                - Permet de préciser la sortie et nous évite reçevoir les données dans 'member'
            */
            openapi: new Operation(
                summary: 'Statistiques d\'exploitation',
                description: 'Permet de voir les statistiques d\'exploitation',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/financiere',
            provider: FinancierStatsProvider::class,
            input: false,
            output: FinancierStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques financières',
                description: 'Permet de voir les statistiques financières',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/stock',
            provider: StockStatsProvider::class,
            input: false,
            output: StockStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques de stock',
                description: 'Permet de voir les statistiques de stock',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/flotte',
            provider: FlotteStatsProvider::class,
            input: false,
            output: FlotteStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques de flotte',
                description: 'Permet de voir les statistiques de flotte',
                security: [['bearerAuth' => []]]
            )
        ),
        // -- Pour le DG -- //
        new Get(
            uriTemplate: '/stats/billetterie',
            provider: BilleterieStatsProvider::class,
            input: false,
            output: BilleterieStatistiqueOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/agent',
            provider: AgentStatsProvider::class,
            input: false,
            output: AgentStatistiqueOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/clients',
            provider: ClientStatsProvider::class,
            input: false,
            output: ClientStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques clients',
                description: 'Volume, nouveaux/actifs, panier moyen et top clients (par voyages et par dépense)',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/fidelite',
            provider: FideliteStatsProvider::class,
            input: false,
            output: FideliteStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques de fidélité',
                description: 'Membres, adhésions, récompenses et top clients du programme de fidélité',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/reservations',
            provider: ReservationStatsProvider::class,
            input: false,
            output: ReservationStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Statistiques de réservation',
                description: 'Volume, statuts, canal, taux de conversion, recette, no-shows et top trajets réservés',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/personnel',
            provider: PersonnelStatsProvider::class,
            input: false,
            output: PersonnelStatistiqueOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/flotte/activite',
            provider: FlotteActiviteStatsProvider::class,
            input: false,
            output: FlotteActiviteOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/ligne/performance',
            provider: LigneStatsProvider::class,
            input: false,
            output: LigneStatistiqueOutput::class,
            openapi: new Operation(
                summary: 'Performance par ligne',
                description: 'Recette, nombre de voyages et de tickets agrégés par ligne (remplace /stats/trajet/performance)',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/caisse',
            provider: CaisseStatsProvider::class,
            input: false,
            output: CaisseOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/courriers',
            provider: CourrierStatsProvider::class,
            input: false,
            output: CourrierStatistiqueOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            uriTemplate: '/stats/bagages',
            provider: BagageStatsProvider::class,
            input: false,
            output: BagageStatistiqueOutput::class,
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        )
    ] /*
        - Vu qu'on utilise une entité non persisté on peut utiliser 'provider' pour personnaliser la récupératon de données
    */
)]
class Statistique
{
}
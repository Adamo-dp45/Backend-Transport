<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurBagageDto;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurCourrierDto;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurOutput;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurTicketDto;
use App\Entity\Output\Bordereau\Chauffeur\BordereauChauffeurVoyageDto;
use App\Entity\User;
use App\Repository\BagageRepository;
use App\Repository\CourrierRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BordereauChauffeurProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly VoyageRepository $voyageRepository,
        private readonly TicketRepository $ticketRepository,
        private readonly CourrierRepository $courrierRepository,
        private readonly BagageRepository $bagageRepository
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

        $voyage = $this->voyageRepository->find($voyageId);
        if(!$voyage || $voyage->getIdentreprise() !== $identreprise) {
            throw new NotFoundHttpException('Voyage introuvable');
        }

        $rawTickets = $this->ticketRepository->findByVoyage($voyageId, $identreprise);
        $rawCourriers = $this->courrierRepository->findByVoyage($voyageId, $identreprise);
        $rawBagages = $this->bagageRepository->findByVoyage($voyageId, $identreprise);

        $tickets = array_map(
            fn($t) => new BordereauChauffeurTicketDto(
                codeticket: $t['codeticket'],
                nomclient: $t['nomclient'] ?? null,
                contactclient: $t['contactclient'] ?? null,
                siegenumero: (int)$t['siegenumero'],
                prix: (int)$t['prix']
            ),
            $rawTickets
        );

        $courriers = array_map(
            fn($c) => new BordereauChauffeurCourrierDto(
                codecourrier: $c['codecourrier'],
                nomexpediteur: $c['nomexpediteur'],
                nomdestinataire: $c['nomdestinataire'],
                garedepart: $c['garedepart'],
                garearrivee: $c['garearrivee'],
                nbcolis: (int)$c['nbcolis'],
                montant: (int)$c['montant'],
                modepaiement: $c['modepaiement']
            ),
            $rawCourriers
        );

        $bagages = array_map(
            fn($b) => new BordereauChauffeurBagageDto(
                codebagage: $b['codebagage'],
                nomclient: $b['nomclient'] ?? 'Client anonyme', // billet anonyme ou bagage historique sans billet
                nature: $b['nature'],
                type: $b['type'],
                poids: (int)$b['poids'],
                montant: (int)$b['montant']
            ),
            $rawBagages
        );

        return new BordereauChauffeurOutput(
            voyage: new BordereauChauffeurVoyageDto(
                id: $voyage->getId(),
                codevoyage: $voyage->getCodevoyage(),
                provenance: $voyage->getProvenance(),
                destination: $voyage->getDestination(),
                datedepartprevue: $voyage->getDatedepartprevue()?->format('d/m/Y H:i') ?? '',
                matricule: $voyage->getCar()?->getMatricule(),
                placestotal: $voyage->getPlacestotal(),
                placesoccupees: $voyage->getTicketsCount() // billets actifs comptés à la volée (ex-colonne 'placesoccupees')
            ),
            generele: (new \DateTime())->format('d/m/Y à H:i'),
            totalTickets: count($tickets),
            totalCourriers: count($courriers),
            totalBagages: count($bagages),
            tickets: $tickets,
            courriers: $courriers,
            bagages: $bagages
        );
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\TicketStatus;
use App\Entity\Bagage;
use App\Entity\Dto\BagageInput;
use App\Entity\Gare;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\BagageRepository;
use App\Repository\TarifbagageRepository;
use App\Repository\TicketRepository;
use App\Repository\VoyageRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BagageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private VoyageRepository $voyageRepository,
        private TarifbagageRepository $tarifbagageRepository,
        private BagageRepository $bagageRepository,
        private TicketRepository $ticketRepository
    )
    {
    }

    /**
     * Résout le billet du client si fourni (optionnel) : il doit alors être VALIDE et de l'entreprise.
     * Le bagage en hérite voyage / gares (provenance, destination). Renvoie null si aucun billet fourni
     * (le bagage est alors simplement ENREGISTRE, sans voyage).
     */
    private function resoudreTicket(BagageInput $data, int $identreprise): Ticket
    {
        if ($data->ticket === null) {
            throw new BadRequestHttpException('Le billet du client est obligatoire');
        }
        $ticket = $this->ticketRepository->findOneBy([
            'id' => $data->ticket,
            'identreprise' => $identreprise,
            'deletedAt' => null,
        ]);
        if (!$ticket) {
            throw new NotFoundHttpException('Billet client introuvable');
        }
        if ($ticket->getStatut() !== TicketStatus::STATUT_VALIDE->value) {
            throw new BadRequestHttpException('Ce billet n\'est pas valide (annulé ou reporté)');
        }
        // Un agent de gare ne rattache un bagage qu'à un billet ÉMIS à SA gare (gare de montée).
        // Admin/central : pas de restriction.
        $user = $this->security->getUser();
        $userGare = $user?->getGare();
        $estAdmin = $user && (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true));
        if ($userGare !== null && !$estAdmin && $ticket->getGare()?->getId() !== $userGare->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez rattacher un bagage qu\'à un billet émis à votre gare (' . $userGare->getLibelle() . ')');
        }
        return $ticket;
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var BagageInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            return $this->handlePost($data, $user->getId(), $identreprise, $operation, $uriVariables, $context);
        }

        if($operation instanceof Patch) {
            return $this->handlePatch($data, $user->getId(), $identreprise, $operation, $uriVariables, $context);
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function handlePost(
        BagageInput $data,
        int $userId,
        int $identreprise,
        $operation,
        $uriVariables,
        $context
    ): Bagage
    {
        // Billet du client OBLIGATOIRE : le bagage SUIT le billet (voyage, gares, identité, canal).
        $ticket = $this->resoudreTicket($data, $identreprise);

        $voyage = $ticket->getVoyage();
        if ($voyage !== null && $voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage du billet est clôturé, enregistrement de bagage impossible');
        }

        [$montant, $tarifbagage, $montantforce] = $this->resoudreMontant(
            $data->poids,
            $data->montant,
            $identreprise
        );

        $bagage = new Bagage();
        $bagage
            ->setIdentreprise($identreprise)
            ->setCreatedBy($userId)
            ->setTicket($ticket)
            ->setVoyage($voyage)
            // Le bagage SUIT le billet : provenance = gare de montée, destination = gare de descente.
            ->setGaredepart($ticket->getGare())
            ->setGaredescente($ticket->getGaredescente())
            ->setNature($data->nature)
            ->setType($data->type)
            ->setPoids((int)$data->poids)
            ->setMontant($montant)
            ->setMontantforce($montantforce)
            ->setTarifbagage($tarifbagage)
            ->setStatut($this->resoudreStatut($voyage))
            ->setCodebagage($this->generateCode($identreprise))
        ;

        return $this->processor->process($bagage, $operation, $uriVariables, $context);
    }

    private function handlePatch(
        BagageInput $data,
        int $userId,
        int $identreprise,
        $operation,
        $uriVariables,
        $context
    ): Bagage
    {
        // Billet du client OBLIGATOIRE : le bagage SUIT le billet (voyage, gares, identité, canal).
        $ticket = $this->resoudreTicket($data, $identreprise);

        $voyage = $ticket->getVoyage();
        if ($voyage !== null && $voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage du billet est clôturé, modification impossible');
        }

        $bagage = $this->bagageRepository->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $identreprise,
            'deletedAt' => null
        ]);

        if(!$bagage) {
            throw new NotFoundHttpException('Bagage invalide');
        }

        // Un agent de gare ne modifie qu'un bagage DÉPOSÉ à SA gare (garedepart). Le findOneBy ci-dessus
        // court-circuite la GareScopeExtension (appel repository direct) : sans ce garde, une gare pourrait
        // modifier le bagage d'une autre gare de la même entreprise. Admin/central : non restreints.
        $user = $this->security->getUser();
        $userGare = $user?->getGare();
        $estAdmin = $user && (in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true));
        if ($userGare !== null && !$estAdmin && $bagage->getGaredepart()?->getId() !== $userGare->getId()) {
            throw new BadRequestHttpException('Vous ne pouvez modifier qu\'un bagage déposé à votre gare (' . $userGare->getLibelle() . ')');
        }

        if(in_array($bagage->getStatut(), [BagageStatus::STATUT_LIVRE->value, BagageStatus::STATUT_PERDU->value])) {
            throw new BadRequestHttpException('Ce bagage ne peut plus être modifié');
        }

        // Seul un bagage encore ENREGISTRE (pas encore rattaché à un voyage) est modifiable.
        if($bagage->getStatut() !== BagageStatus::STATUT_ENREGISTRE->value) {
            throw new BadRequestHttpException('Seul un bagage enregistré peut être modifié. Statut actuel : ' . $bagage->getStatut());
        }

        [$montant, $tarifbagage, $montantforce] = $this->resoudreMontant(
            $data->poids,
            $data->montant,
            $identreprise
        );

        $bagage
            ->setUpdatedBy($userId)
            ->setTicket($ticket)
            ->setNature($data->nature)
            ->setType($data->type)
            ->setPoids((int)$data->poids)
            ->setMontant($montant)
            ->setMontantforce($montantforce)
            ->setTarifbagage($tarifbagage)
            ->setVoyage($voyage)
            ->setGaredepart($ticket->getGare())
            ->setGaredescente($ticket->getGaredescente())
            ->setStatut($this->resoudreStatut($voyage))
            ->setUpdatedAt(new \DateTimeImmutable())
        ;

        return $this->processor->process($bagage, $operation, $uriVariables, $context);
    }

    // NB : l'ancienne méthode resoudreGares() (choix manuel des gares + forçage à la gare de l'agent)
    // a été retirée. Un bagage est désormais toujours rattaché à un billet : provenance et destination
    // proviennent directement du billet (ticket->getGare() / ticket->getGaredescente()).

    private function resoudreStatut(?Voyage $voyage): string
    {
        if($voyage === null) {
            return BagageStatus::STATUT_ENREGISTRE->value;
        }
        if($voyage->getDatearriveereelle() !== null) {
            return BagageStatus::STATUT_LIVRE->value; // voyage clôturé
        }
        // EMBARQUE seulement si le voyage est PARTI (départ réel). Sinon le bagage reste ENREGISTRE
        // (le passage ENREGISTRE -> EMBARQUE est propagé au départ par VoyageDepartService).
        if($voyage->getDatedepartreelle() !== null) {
            return BagageStatus::STATUT_EMBARQUE->value;
        }
        return BagageStatus::STATUT_ENREGISTRE->value;
    }

    /**
     * Permet de résoudre le montant final
     *  - Va chercher le tarif correspondant au poids
     *  - Si tarif trouvé et montant non fourni → montant du tarif
     *  - Si tarif trouvé et montant fourni différent → montant forcé et 'tarifbagage' conservé pour historique
     *  - Si aucun tarif trouvé et montant fourni → montant forcé
     *  - Si aucun tarif trouvé et montant non fourni → erreur
     */
    private function resoudreMontant(float $poids, ?int $montantFourni, int $identreprise): array
    {
        $tarifbagage = $this->tarifbagageRepository->findTarifForPoids($poids, $identreprise);

        if($tarifbagage !== null) {
            $montantCalcule = $tarifbagage->getMontant();
            if($montantFourni !== null && $montantFourni !== $montantCalcule) {
                return [
                    $montantFourni,
                    $tarifbagage,
                    true
                ]; /*
                    - L'agent force un montant différent alors on garde le tarif pour l'historique
                */
            }
            return [
                $montantCalcule,
                $tarifbagage,
                false
            ];
        }

        if($montantFourni === null) {
            throw new BadRequestHttpException('Aucun tarif trouvé pour ' . $poids . ' kg. Veuillez saisir le montant manuellement');
        }

        return [$montantFourni, null, true];
    }

    private function generateCode(int $identreprise): string
    {
        $count = $this->bagageRepository->count([
            'identreprise' => $identreprise,
            'deletedAt' => null /*
                - Peut être bloquant si on n'avait pas le validator 'UniquePerEntreprise'
            */
        ]);
        return 'BAG-' . date('Y') . '-' . ($count + 1);
    }
}
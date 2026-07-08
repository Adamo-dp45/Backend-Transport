<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\TicketStatus;
use App\Entity\Dto\DescendreInput;
use App\Entity\Ticket;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * « Le passager descend en cours de route » (PATCH /tickets/{id}/descendre).
 *
 * Enregistre la gare de descente RÉELLE sur un billet VALIDE, en amont de sa descente vendue, pour
 * libérer le siège en aval et permettre sa REVENTE. On ne touche NI au prix NI à la recette : le
 * client a payé jusqu'à 'garedescente', il descend simplement plus tôt. L'occupation du siège
 * ('td' dans SiegeStateProvider / TicketProcessor::assertSiegeLibre) utilise 'garedescenteEffective()'.
 */
class DescendreTicketProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var DescendreInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if ($data->gare === null) {
            throw new BadRequestHttpException('La gare de descente réelle est obligatoire');
        }

        // 1. Billet (périmètre entreprise) — seul un billet VALIDE occupe un siège
        $ticket = $this->em->getRepository(Ticket::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$ticket) {
            throw new NotFoundHttpException('Billet introuvable');
        }
        if ($ticket->getStatut() !== TicketStatus::STATUT_VALIDE->value) {
            throw new BadRequestHttpException('Ce billet n\'est pas valide (déjà reporté ou annulé)');
        }

        $voyage = $ticket->getVoyage();
        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : libération de siège impossible');
        }

        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce billet n\'est rattaché à aucune ligne');
        }

        // 2. Ordres des arrêts de la ligne
        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }

        $gareReelleId = $data->gare->getId();
        $monteeId = $ticket->getGare()?->getId();
        $descenteVendueId = $ticket->getGaredescente()?->getId();

        if (!isset($ordreParGare[$gareReelleId])) {
            throw new BadRequestHttpException('La gare de descente n\'est pas un arrêt de la ligne du voyage');
        }
        if ($monteeId === null || $descenteVendueId === null
            || !isset($ordreParGare[$monteeId], $ordreParGare[$descenteVendueId])) {
            throw new BadRequestHttpException('Le tronçon du billet est incomplet');
        }

        // Sécurité métier : depuis OÙ peut-on libérer un siège ?
        // - Le COMMERCIAL du voyage le fait depuis la POSITION COURANTE du car ('garecourante').
        // - Sinon, un agent rattaché à une gare ne le fait qu'à SA gare. Central sans gare : libre.
        $estCommercial = $voyage->getCommercial() && $voyage->getCommercial()->getId() === $user->getId();
        if ($estCommercial) {
            $gc = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
            if ($gc !== null && $gareReelleId !== $gc->getId()) {
                throw new BadRequestHttpException('Vous libérez un siège depuis la position actuelle du car (' . $gc->getLibelle() . ')');
            }
        } else {
            $userGare = $user->getGare();
            if ($userGare !== null && $gareReelleId !== $userGare->getId()) {
                throw new BadRequestHttpException('Vous ne pouvez libérer un siège qu\'à votre gare (' . $userGare->getLibelle() . ')');
            }
        }

        // 3. La descente réelle doit être APRÈS la montée et AU PLUS TARD à la descente vendue
        $ordreReelle = $ordreParGare[$gareReelleId];
        $ordreMontee = $ordreParGare[$monteeId];
        $ordreDescenteVendue = $ordreParGare[$descenteVendueId];

        if ($ordreReelle <= $ordreMontee) {
            throw new BadRequestHttpException('Le passager ne peut pas descendre avant ou à sa gare de montée');
        }
        if ($ordreReelle > $ordreDescenteVendue) {
            throw new BadRequestHttpException('La descente réelle ne peut pas être après la descente prévue du billet');
        }

        // 4. On enregistre la descente réelle : le siège se libère en aval « mécaniquement »
        // (SiegeStateProvider et assertSiegeLibre utilisent garedescenteEffective()).
        $ticket
            ->setGaredescentereelle($data->gare)
            ->setUpdatedBy($user->getId());

        return $this->processor->process($ticket, $operation, $uriVariables, $context);
    }
}

<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\TicketStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Bagage;
use App\Entity\Dto\DesistementInput;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Désistement d'un billet (POST /tickets/{id}/desister).
 *
 * Principe : on ne mute jamais le billet d'origine en place — on CHAÎNE.
 *  - ANNULATION : le billet d'origine passe ANNULE (remboursement intégral) ; le siège est libéré.
 *  - REPORT     : le billet d'origine passe REPORTE et un NOUVEAU billet VALIDE est créé sur un voyage
 *                 de la MÊME ligne (tronçon + prix conservés), lié via 'ticketOrigine'. Le siège du
 *                 voyage d'origine est libéré, un siège du voyage cible est réservé.
 *
 * Le siège se libère « mécaniquement » : aucune écriture sur les sièges, il suffit que les requêtes
 * d'occupation (SiegeStateProvider, TicketProcessor::assertSiegeLibre, Voyage::getTicketsCount)
 * ne comptent que les billets VALIDE.
 */
class DesistementProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var DesistementInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        // 1. Billet d'origine (dans le périmètre entreprise)
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

        if ($ticket->getVoyage()->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage d\'origine est clôturé : désistement impossible');
        }

        // Sécurité métier : seule la gare émettrice (gare de montée du billet) ou un admin peut désister.
        // Un utilisateur central sans gare (userGare null) passe ; les admins de gare ont leur bypass
        // dans le voter de la permission TICKET_MODIFIER (sécurité de l'opération).
        $userGare = $user->getGare();
        if ($userGare !== null && $ticket->getGare()?->getId() !== $userGare->getId()) {
            throw new BadRequestHttpException('Seule la gare émettrice du billet peut traiter ce désistement');
        }

        $now = new \DateTimeImmutable();

        return match ($data->mode) {
            'ANNULATION' => $this->annuler($ticket, $data, $user, $now, $operation, $uriVariables, $context),
            'REPORT' => $this->reporter($ticket, $data, $user, $entrepriseId, $now, $operation, $uriVariables, $context),
            default => throw new BadRequestHttpException('Mode de désistement invalide (REPORT ou ANNULATION)'),
        };
    }

    /**
     * Annulation : le billet passe ANNULE, remboursement intégral implicite (= prix). Siège libéré.
     */
    private function annuler(Ticket $ticket, DesistementInput $data, User $user, \DateTimeImmutable $now, Operation $operation, array $uriVariables, array $context): Ticket
    {
        $ticket
            ->setStatut(TicketStatus::STATUT_ANNULE->value)
            ->setDatedesistement($now)
            ->setMotifdesistement($data->motif)
            ->setUpdatedBy($user->getId());

        // Le client ne part plus → ses bagages rattachés à ce billet sont annulés (ils ne partent pas).
        // On ne touche pas aux bagages déjà livrés/perdus/annulés.
        $this->annulerBagagesDuTicket($ticket, $user);

        $this->activiteLogger->log(
            ActiviteLogger::TICKET_ANNULE,
            'Billet ' . $ticket->getCodeticket() . ' annulé' . ($data->motif ? ' (' . $data->motif . ')' : ''),
            'Ticket',
            $ticket->getId()
        );

        return $this->processor->process($ticket, $operation, $uriVariables, $context);
    }

    /**
     * Annule en cascade les bagages liés au billet (ceux encore enregistrés / embarqués) : le passager
     * ne voyageant plus, ses bagages ne partent pas. Les entités sont managées → flushées avec le billet.
     */
    private function annulerBagagesDuTicket(Ticket $ticket, User $user): void
    {
        $bagages = $this->em->getRepository(Bagage::class)->findBy([
            'ticket' => $ticket,
            'statut' => [BagageStatus::STATUT_ENREGISTRE->value, BagageStatus::STATUT_EMBARQUE->value],
            'deletedAt' => null,
        ]);

        foreach ($bagages as $bagage) {
            $bagage
                ->setStatut(BagageStatus::STATUT_ANNULE->value)
                ->setUpdatedBy($user->getId())
                ->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    /**
     * Report : crée un nouveau billet VALIDE sur le voyage cible (même ligne, même tronçon, même prix)
     * et bascule le billet d'origine en REPORTE.
     */
    private function reporter(Ticket $ticket, DesistementInput $data, User $user, int $entrepriseId, \DateTimeImmutable $now, Operation $operation, array $uriVariables, array $context): Ticket
    {
        if (!$data->voyage) {
            throw new BadRequestHttpException('Le voyage de report est obligatoire');
        }
        if (!$data->siege) {
            throw new BadRequestHttpException('Le siège sur le voyage de report est obligatoire');
        }

        // Recharger le voyage cible dans le périmètre entreprise
        $voyageCible = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $data->voyage->getId(),
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$voyageCible) {
            throw new NotFoundHttpException('Voyage de report introuvable');
        }
        if ($voyageCible->getId() === $ticket->getVoyage()->getId()) {
            throw new BadRequestHttpException('Le voyage de report doit être différent du voyage d\'origine');
        }
        if ($voyageCible->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage de report est clôturé');
        }
        if (!$voyageCible->getCar()) {
            throw new BadRequestHttpException('Aucun véhicule affecté au voyage de report');
        }

        // Report limité à la MÊME ligne → mêmes arrêts, donc tronçon et tarif garantis identiques
        $ligneOrigine = $ticket->getVoyage()->getLigne();
        $ligneCible = $voyageCible->getLigne();
        if (!$ligneCible || !$ligneOrigine || $ligneCible->getId() !== $ligneOrigine->getId()) {
            throw new BadRequestHttpException('Le report n\'est possible que vers un voyage de la même ligne');
        }

        // Le siège choisi doit appartenir au car du voyage cible
        if ($data->siege->getCar()?->getId() !== $voyageCible->getCar()->getId()) {
            throw new BadRequestHttpException('Ce siège n\'appartient pas au véhicule du voyage de report');
        }

        // Tronçon conservé : on reprend les gares de montée / descente du billet d'origine
        $garemontee = $ticket->getGare();
        $garedescente = $ticket->getGaredescente();

        // Le siège doit être libre sur ce tronçon du voyage cible (priorité gare amont, comme à la vente)
        $this->assertSiegeLibre($voyageCible, $data->siege, $entrepriseId, $ligneCible, $garemontee, $garedescente);

        // Nouveau billet : recopie client / tronçon / prix / remise / bénéficiaire (report = même prix)
        $nouveau = (new Ticket())
            ->setVoyage($voyageCible)
            ->setSiege($data->siege)
            ->setGare($garemontee)
            ->setGaredescente($garedescente)
            ->setNomclient($ticket->getNomclient())
            ->setContactclient($ticket->getContactclient())
            ->setPrix($ticket->getPrix())
            ->setRemise($ticket->getRemise())
            ->setBeneficiaire($ticket->getBeneficiaire())
            ->setStatut(TicketStatus::STATUT_VALIDE->value)
            ->setTicketOrigine($ticket)
            ->setIdentreprise($entrepriseId)
            ->setCreatedBy($user->getId());
        $nouveau->setCodeticket($voyageCible->getCodevoyage() . '-' . $this->generateCode($entrepriseId, $voyageCible->getId()));

        // Le billet d'origine bascule REPORTE → son siège est libéré sur le voyage d'origine
        $ticket
            ->setStatut(TicketStatus::STATUT_REPORTE->value)
            ->setDatedesistement($now)
            ->setMotifdesistement($data->motif)
            ->setUpdatedBy($user->getId());

        $this->em->persist($nouveau);

        $this->activiteLogger->log(
            ActiviteLogger::TICKET_REPORTE,
            'Billet ' . $ticket->getCodeticket() . ' reporté sur le voyage ' . $voyageCible->getCodevoyage(),
            'Ticket',
            $ticket->getId()
        );

        // Le flush du persist_processor enregistre le nouveau billet ET la bascule de l'origine (entité managée)
        return $this->processor->process($nouveau, $operation, $uriVariables, $context);
    }

    /**
     * Vérifie qu'un siège est libre sur le tronçon [montée, descente) du voyage, avec PRIORITÉ À LA GARE
     * AMONT — même logique qu'à la vente (TicketProcessor::assertSiegeLibre), mais on ne compte que les
     * billets VALIDE (un billet REPORTE/ANNULE ne bloque plus le siège).
     */
    private function assertSiegeLibre(Voyage $voyage, Siege $siege, int $entrepriseId, Ligne $ligne, ?Gare $garemontee, ?Gare $garedescente): void
    {
        if (!$garemontee || !$garedescente) {
            throw new BadRequestHttpException('Le tronçon du billet d\'origine est incomplet');
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }

        $monteeId = $garemontee->getId();
        $descenteId = $garedescente->getId();
        if (!isset($ordreParGare[$monteeId], $ordreParGare[$descenteId])) {
            throw new BadRequestHttpException('Le tronçon du billet n\'est pas desservi par le voyage de report');
        }
        $ordreMontee = $ordreParGare[$monteeId];
        $ordreDescente = $ordreParGare[$descenteId];
        if ($ordreMontee >= $ordreDescente) {
            throw new BadRequestHttpException('Tronçon invalide (descente avant montée)');
        }
        $ordreTerminus = $ordreParGare[$ligne->getGareterminus()->getId()] ?? PHP_INT_MAX;

        $existants = $this->em->getRepository(Ticket::class)->findBy([
            'voyage' => $voyage,
            'siege' => $siege,
            'identreprise' => $entrepriseId,
            'statut' => TicketStatus::STATUT_VALIDE->value,
            'deletedAt' => null,
        ]);

        foreach ($existants as $t) {
            $tm = $ordreParGare[$t->getGare()?->getId()] ?? null;
            // Descente EFFECTIVE (réelle si descendu en route, sinon vendue)
            $descenteEff = $t->getGaredescenteEffective();
            $td = $descenteEff
                ? ($ordreParGare[$descenteEff->getId()] ?? null)
                : $ordreTerminus;
            if ($tm === null || $td === null) {
                continue;
            }
            if ($tm <= $ordreMontee && $td > $ordreMontee) {
                throw new BadRequestHttpException('Ce siège est déjà occupé sur ce tronçon du voyage de report');
            }
        }
    }

    private function generateCode(int $entrepriseId, int $voyageId): string
    {
        $count = $this->em->getRepository(Ticket::class)->count([
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
            'voyage' => $voyageId,
        ]);

        return 'TCK-' . date('Y') . '-' . ($count + 1);
    }
}

<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\CourrierStatus;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Enum\TicketStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Detailpersonnel;
use App\Entity\Interface\HasLockGuard;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\UserRepository;
use App\Security\GareGuard;
use App\Security\VoyageGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SoftDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private RemoveProcessor $removeProcessor,
        private Security $security,
        private VoyageGuard $voyageGuard,
        private GareGuard $gareGuard,
        private ActiviteLogger $activiteLogger,
        private UserRepository $userRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /**
         * @var User
         */
        $user = $this->security->getUser();

        // Suppression d'un voyage = planification : réservée à la gare d'ORIGINE (+ admin/central)
        if($data instanceof Voyage) {
            $this->voyageGuard->assertPeutPlanifier($user, $data);
        }

        // Suppression d'un ticket : réservée à la gare ÉMETTRICE (gare de montée) ; la lecture reste large
        if($data instanceof Ticket) {
            $this->gareGuard->assertEstGare($user, $data->getGare(), 'Seule la gare émettrice peut supprimer ce ticket');
            $this->assertSuppressionBilletAutorisee($data);
        }

        // Suppression d'un bagage : réservée à la gare de DÉPÔT (garedepart) — la GareScopeExtension autorise
        // aussi la gare de descente à le LIRE, d'où ce garde explicite — et uniquement tant qu'il est
        // ENREGISTRE (une fois embarqué il est en transit / livré). Cohérent avec sa modification.
        if($data instanceof Bagage) {
            $this->gareGuard->assertEstGare($user, $data->getGaredepart(), 'Seule la gare de dépôt peut supprimer ce bagage');
            if($data->getStatut() !== BagageStatus::STATUT_ENREGISTRE->value) {
                throw new BadRequestHttpException('Seul un bagage enregistré (pas encore embarqué) peut être supprimé. Statut actuel : ' . $data->getStatut());
            }
        }

        // Suppression d'un courrier : mêmes garanties que l'annulation — seulement tant qu'il est EN_ATTENTE
        // (pas encore parti) et réservée à la gare ÉMETTRICE (déduite du créateur). Sans ce garde, un courrier
        // à n'importe quel statut/gare pouvait être supprimé sans trace (faille comblée).
        if($data instanceof Courrier) {
            if($data->getStatut() !== CourrierStatus::STATUT_EN_ATTENTE->value) {
                throw new BadRequestHttpException('Seul un courrier en attente peut être supprimé. Statut actuel : ' . $data->getStatut());
            }
            $createur = $data->getCreatedBy() ? $this->userRepository->find($data->getCreatedBy()) : null;
            $this->gareGuard->assertEstGare($user, $createur?->getGare(), 'Seule la gare émettrice peut supprimer ce courrier');
        }

        if($data instanceof HasSoftDeleteGuard) { /*
            - On vérifie des blockers si l'entité les supporte
        */
            $blockers = $data->getSoftDeleteBlockers();
            if(!empty($blockers)) {
                /*
                    Les 'blockers' énoncent le CONSTAT (« rattachée à 3 gare(s) active(s) ») ; on y
                    ajoute la MARCHE À SUIVRE, sans quoi l'agent reçoit un refus sans issue. Un
                    référentiel encore utilisé ne se supprime pas — on le détache d'abord, ou on le
                    laisse en place : le garder ne gêne personne, il ne fausse aucun historique.
                */
                throw new UnprocessableEntityHttpException(
                    implode(' ', $blockers)
                    . ' Détachez ou supprimez d\'abord ces éléments. En attendant, cet enregistrement peut rester en place : il n\'y a aucun risque à le conserver.'
                );
            }
        }

        if($data instanceof Detailpersonnel) {
            if($data->getVoyage()?->getDatearriveereelle() !== null) {
                throw new BadRequestHttpException('Impossible de désaffecter un personnel d\'un voyage clôturé');
            }
            // Désaffectation liée à un voyage : interdite à la gare de destination
            if($data->getVoyage()) {
                $this->voyageGuard->assertPeutGerer($user, $data->getVoyage());
            }
            if($data->getDepannage()?->getStatut() === DepannageStatus::CLOTURE->value) {
                throw new BadRequestHttpException('Impossible de désaffecter un personnel d\'un dépannage clôturé');
            }
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if(method_exists($data, 'setDeletedAt')) {
            $data
                ->setDeletedAt(new \DateTimeImmutable())
                ->setDeletedBy($user->getId())
                ->setIsEtatdelete(true)
            ;
        }

        // Suppressions : événements critiques tracés dans le journal d'activité (auteur = suppresseur).
        if($data instanceof Ticket) {
            $this->activiteLogger->log(
                ActiviteLogger::TICKET_SUPPRIME,
                'Billet ' . $data->getCodeticket() . ' supprimé',
                'Ticket',
                $data->getId()
            );
        }
        if($data instanceof Bagage) {
            $this->activiteLogger->log(
                ActiviteLogger::BAGAGE_SUPPRIME,
                'Bagage ' . $data->getCodebagage() . ' supprimé',
                'Bagage',
                $data->getId()
            );
        }
        if($data instanceof Courrier) {
            $this->activiteLogger->log(
                ActiviteLogger::COURRIER_SUPPRIME,
                'Courrier ' . $data->getCodecourrier() . ' supprimé',
                'Courrier',
                $data->getId()
            );
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * ANTI « vente hors-livre » : un billet PAYÉ (VALIDE) ne se supprime plus une fois que le car a
     * ATTEINT/DÉPASSÉ la gare de MONTÉE du passager (il a voyagé). Le retirer passe alors par une
     * ANNULATION (tracée, avec motif, contrôlée). Intermédiaire-aware : un passager en aval reste
     * supprimable tant que le car ne l'a pas rejoint ; avant le départ réel, la suppression est libre
     * (correction d'une erreur de saisie). Ferme aussi l'incohérence « billet supprimé absent du
     * manifeste mais compté en recette » puisqu'un billet payé embarqué ne quitte plus le livre.
     */
    private function assertSuppressionBilletAutorisee(Ticket $ticket): void
    {
        if ($ticket->getStatut() !== TicketStatus::STATUT_VALIDE->value) {
            return; // billets déjà annulés/reportés : suppression = simple purge, hors recette
        }

        $voyage = $ticket->getVoyage();
        $ligne = $voyage?->getLigne();
        if ($voyage === null || $voyage->getDatedepartreelle() === null || $ligne === null) {
            return; // voyage pas encore parti (ou hors ligne) : suppression libre
        }

        $ordreParGare = [];
        foreach ($ligne->getArrets() as $a) {
            $ordreParGare[$a->getGare()->getId()] = (int) $a->getOrdre();
        }
        $position = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        $ordrePosition = $position ? ($ordreParGare[$position->getId()] ?? 0) : 0;
        $ordreMontee = $ordreParGare[$ticket->getGare()?->getId()] ?? PHP_INT_MAX;

        if ($ordrePosition >= $ordreMontee) {
            throw new BadRequestHttpException(
                'Ce billet payé ne peut plus être supprimé : le car a atteint la gare de montée du passager. Utilisez une annulation si nécessaire.'
            );
        }
    }
}

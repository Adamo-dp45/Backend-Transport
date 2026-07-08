<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\RemoveProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Bagage;
use App\Entity\Detailpersonnel;
use App\Entity\Interface\HasLockGuard;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
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
        private ActiviteLogger $activiteLogger
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

        if($data instanceof HasSoftDeleteGuard) { /*
            - On vérifie des blockers si l'entité les supporte
        */
            $blockers = $data->getSoftDeleteBlockers();
            if(!empty($blockers)) {
                throw new UnprocessableEntityHttpException(implode(' ', $blockers));
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

        // Suppression de billet : événement critique tracé dans le journal d'activité.
        if($data instanceof Ticket) {
            $this->activiteLogger->log(
                ActiviteLogger::TICKET_SUPPRIME,
                'Billet ' . $data->getCodeticket() . ' supprimé',
                'Ticket',
                $data->getId()
            );
        }

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

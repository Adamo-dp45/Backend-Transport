<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\VoyageDepartService;
use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\CourrierStatus;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Réception d'un voyage par une gare INTERMÉDIAIRE.
 *
 * Une gare intermédiaire ne clôture pas le voyage ; l'agent confirme le passage du véhicule à SA gare
 * et tous les colis/bagages qui y descendent basculent automatiquement :
 *  - Courriers (garearrivee = gare, EN_TRANSIT) -> RECEPTIONNE
 *  - Bagages   (garedescente = gare, EMBARQUE)  -> LIVRE
 */
class ReceptionnerVoyageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private VoyageGuard $guard,
        private ActiviteLogger $activiteLogger,
        private VoyageDepartService $departService
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Voyage $data */

        /** @var User $user */
        $user = $this->security->getUser();

        // Autorisation : réception réservée à une gare intermédiaire (ni provenance ni destination),
        // sur la ligne du voyage, et seulement si le voyage n'est pas clôturé.
        $this->guard->assertPeutReceptionner($user, $data);

        $gare = $user->getGare();
        $entrepriseId = $user->getEntreprise()->getId();
        $now = new \DateTimeImmutable();

        // Fallback : si le départ réel n'a pas été marqué, une réception en aval prouve que le car est
        // parti → on le pose maintenant (bascule les colis EN_ATTENTE -> EN_TRANSIT AVANT la réception
        // ci-dessous, pour que ceux qui descendent ici soient bien réceptionnés).
        $this->departService->marquerDepart($data, $user->getId());

        // Courriers qui descendent à cette gare : EN_TRANSIT -> RECEPTIONNE
        $courriers = $this->em->getRepository(Courrier::class)->findBy([
            'voyage' => $data,
            'garearrivee' => $gare,
            'statut' => CourrierStatus::STATUT_EN_TRANSIT->value,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        foreach($courriers as $courrier) {
            $courrier
                ->setStatut(CourrierStatus::STATUT_RECEPTIONNE->value)
                ->setUpdatedBy($user->getId())
                ->setUpdatedAt($now);
        }

        // Bagages qui descendent à cette gare : EMBARQUE -> LIVRE
        $bagages = $this->em->getRepository(Bagage::class)->findBy([
            'voyage' => $data,
            'garedescente' => $gare,
            'statut' => BagageStatus::STATUT_EMBARQUE->value,
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        foreach ($bagages as $bagage) {
            $bagage
                ->setStatut(BagageStatus::STATUT_LIVRE->value)
                ->setUpdatedBy($user->getId())
                ->setUpdatedAt($now);
        }

        // Avance la POSITION COURANTE du car à cette gare intermédiaire (avancement monotone) : c'est
        // depuis cette gare que le commercial à bord vendra désormais (« la carte s'actualise »).
        $ligne = $data->getLigne();
        if ($ligne !== null && $gare !== null) {
            $ordreParGare = [];
            foreach ($ligne->getArrets() as $arret) {
                $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
            }
            $cibleOrdre = $ordreParGare[$gare->getId()] ?? null;
            $courante = $data->getGarecourante() ?? $data->getOrigineEffective();
            $courantOrdre = $courante ? ($ordreParGare[$courante->getId()] ?? 0) : 0;
            if ($cibleOrdre !== null && $cibleOrdre > $courantOrdre) {
                $data->setGarecourante($gare);
            }
        }

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_RECEPTION,
            sprintf('Voyage réceptionné à %s', $gare?->getLibelle() ?? 'gare intermédiaire'),
            $data->getId()
        );

        // Le flush du persist_processor enregistre les changements de statut (entités managées)
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

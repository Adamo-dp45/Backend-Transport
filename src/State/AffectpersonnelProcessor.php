<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Enum\DepannageStatus;
use App\Entity\Depannage;
use App\Entity\Detailpersonnel;
use App\Entity\Dto\AffectpersonnelInput;
use App\Entity\User;
use App\Entity\Voyage;
use App\Repository\PersonnelRepository;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AffectpersonnelProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private PersonnelRepository $personnelRepository,
        private EntityManagerInterface $em,
        private VoyageGuard $guard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AffectpersonnelInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();
        $personnel = $this->personnelRepository->findOneBy([
            'id' => $data->personnel,
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);

        if(!$personnel) {
            throw new NotFoundHttpException('Personnel introuvable dans votre entreprise');
        }

        if($operation->getName() === 'Affect-depannage') {
            $depannage = $this->em->getRepository(Depannage::class)->findOneBy([
                'id' => $uriVariables['id'],
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$depannage) {
                throw new NotFoundHttpException('Dépannage introuvable');
            }

            if($depannage->getStatut() === DepannageStatus::CLOTURE->value) {
                throw new BadRequestHttpException('Dépannage clôturé non modifiable');
            }

            foreach($depannage->getDetailpersonnels() as $dp) {
                if($dp->getPersonnel()->getId() === $personnel->getId()) {
                    throw new BadRequestHttpException('Ce personnel est déjà affecté à ce dépannage');
                }
            }

            $detail = new Detailpersonnel();
            $detail
                ->setPersonnel($personnel)
                ->setDepannage($depannage)
                ->setMotif($data->motif)
                ->setIdentreprise($entrepriseId); // isolation tenant
            $this->em->persist($detail);

            return $this->processor->process($depannage, $operation, $uriVariables, $context);
        }

        if($operation->getName() === 'Affect-voyage') {
            $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
                'id' => $uriVariables['id'],
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$voyage) {
                throw new NotFoundHttpException('Voyage introuvable');
            }

            // Affectation interdite à la gare de destination
            $this->guard->assertPeutGerer($user, $voyage);

            if($voyage->getDatearriveereelle() !== null) {
                throw new BadRequestHttpException('Ce voyage est clôturé : impossible d\'affecter du personnel');
            }

            foreach($voyage->getDetailpersonnels() as $dp) {
                if($dp->getPersonnel()->getId() === $personnel->getId()) {
                    throw new BadRequestHttpException('Ce personnel est déjà affecté à ce voyage');
                }
            }

            $detail = new DetailPersonnel();
            $detail
                ->setPersonnel($personnel)
                ->setVoyage($voyage)
                ->setMotif($data->motif)
                ->setIdentreprise($entrepriseId); // isolation tenant
            $this->em->persist($detail);

            $this->activiteLogger->voyage(
                ActiviteLogger::VOYAGE_PERSONNEL,
                sprintf('Personnel affecté : %s %s', $personnel->getPrenom(), $personnel->getNom()),
                $voyage->getId()
            );

            return $this->processor->process($voyage, $operation, $uriVariables, $context);
        }
    }
}

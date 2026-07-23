<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CapaciteService;
use App\Domain\Service\CarStatutService;
use App\Domain\Service\ReaffectationSiegeService;
use App\Entity\Dto\AffectcarInput;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AffectcarProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private CarStatutService $carStatutService,
        private VoyageGuard $guard,
        private CapaciteService $capaciteService,
        private ReaffectationSiegeService $reaffectationSiege,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AffectcarInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);

        if(!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        // Exploitation (affecter/CHANGER le car) : ouverte à toute gare de la route effective sauf terminus
        // → permet à la gare où le car est tombé en panne de le remplacer.
        $this->guard->assertPeutGerer($user, $voyage);

        if($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : impossible d\'affecter un véhicule');
        }

        $car = $data->car;
        $ancienCar = $voyage->getCar();

        // Même car → rien à faire (idempotent)
        if($ancienCar && $ancienCar->getId() === $car->getId()) {
            return $this->processor->process($voyage, $operation, $uriVariables, $context);
        }

        // Le nouveau car ne doit pas déjà rouler sur un autre voyage en cours
        $voyageActif = $this->em->getRepository(Voyage::class)->findOneBy([
            'car' => $car,
            'identreprise' => $entrepriseId,
            'datearriveereelle' => null,
            'deletedAt' => null
        ]);
        if($voyageActif && $voyageActif->getId() !== $voyage->getId()) {
            throw new BadRequestHttpException(
                sprintf(
                    'Ce véhicule est déjà affecté au voyage "%s" (%s → %s) qui est en cours. Clôturez ce voyage avant de l\'affecter à un autre.',
                    $voyageActif->getCodevoyage(),
                    $voyageActif->getProvenance(),
                    $voyageActif->getDestination()
                )
            );
        }

        /*
            Le véhicule doit pouvoir HONORER ce qui est déjà engagé : billets émis ET réservations qui
            tiennent une place (payées, ou impayées en cours de paiement). Sinon on découvrirait le
            problème au guichet, à l'émission du billet, face à un client qui a déjà payé.

            Trois corrections par rapport à l'ancienne garde :
             - elle ne s'appliquait qu'au CHANGEMENT de car ; or des réservations existent avant même
               la première affectation (payer ne dépend pas du car) ;
             - elle ignorait les réservations ;
             - elle comparait un TOTAL de billets, alors qu'avec la vente par tronçon un même siège en
               porte plusieurs sur des tronçons disjoints — elle refusait donc des cas parfaitement
               valables. On compare désormais l'occupation maximale d'un segment.
        */
        $occupation = $this->capaciteService->occupationMaximale($voyage, $entrepriseId);
        if($occupation > $car->getNbrsiege()) {
            throw new BadRequestHttpException(sprintf(
                'Ce véhicule (%d place(s)) ne peut pas accueillir les %d place(s) déjà engagées sur ce voyage (billets émis et réservations). Choisissez un véhicule plus grand.',
                $car->getNbrsiege(),
                $occupation
            ));
        }

        $this->carStatutService->verifierDisponibiliteVoyage($car);

        // Remplacement : rattacher les sièges des billets au NOUVEAU car. Les sièges appartiennent au
        // car, pas au voyage — sans cela un billet garderait un siège de l'ancien car, invisible du plan
        // et de l'occupation, et sa place serait revendue. On reprend le même numéro s'il existe, sinon
        // on rassoit le passager sur un siège libre (cf. ReaffectationSiegeService). On le fait AVANT de
        // basculer les statuts pour qu'un refus (car trop petit pour rasseoir tout le monde) ne laisse
        // aucun effet de bord.
        if($ancienCar) {
            $this->reaffectationSiege->reaffecter($voyage, $car, $entrepriseId);
            $this->carStatutService->mettreDisponible($ancienCar);
        }

        $this->carStatutService->mettreEnVoyage($car);
        $voyage->setCar($car);
        $voyage->setPlacesTotal($car->getNbrsiege());

        // Journal : tracer l'affectation / le changement de car. Ce chemin dédié (/affectcar) ne
        // journalisait rien, à la différence de la modification du voyage (VoyageProcessor) — un
        // changement de véhicule passait donc inaperçu selon l'écran utilisé. On aligne les deux.
        $ancienMat = $ancienCar?->getMatricule();
        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_CAR,
            $ancienMat !== null
                ? sprintf('Car changé : %s → %s', $ancienMat, $car->getMatricule())
                : sprintf('Car affecté : %s', $car->getMatricule()),
            $voyage->getId()
        );

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}

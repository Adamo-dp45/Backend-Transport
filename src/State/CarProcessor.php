<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Car;
use App\Entity\Siege;
use App\Entity\Ticket;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CarProcessor implements ProcessorInterface
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
        /**
         * @var User
         */
        $user = $this->security->getUser();
        $identreprise = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            $data
                ->setIdentreprise($identreprise)
                ->setCreatedBy($user->getId());
            $this->synchroniserSieges($data, $identreprise); /*
                - Génération initiale des sièges
            */
        }

        if($operation instanceof Patch) {
            $data->setUpdatedBy($user->getId());
            $original = $this->em->getUnitOfWork()->getOriginalEntityData($data);
            $gaucheChange = $data->getSiegesGauche() !== ($original['sieges_gauche'] ?? null); /*
                - Valeurs telles qu'en base avant modification
            */
            $droiteChange = $data->getSiegesDroite() !== ($original['sieges_droite'] ?? null);
            $nbrChange = $data->getNbrsiege() !== ($original['nbrsiege'] ?? null);

            if($gaucheChange || $droiteChange || $nbrChange) { /*
                - On synchronise les sièges si la disposition OU le nombre total change
            */
                $this->synchroniserSieges($data, $identreprise);
            }

            if($nbrChange) { /*
                - La capacité (placestotal) des voyages en cours suit le nouveau nombre de sièges
            */
                $this->synchroniserPlacestotalVoyages($data, $identreprise);
            }
        }

        $data->setUpdatedAt(new \DateTimeImmutable());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Synchronise les sièges du car avec sa disposition (sieges_gauche/droite) et son
     * nombre total (nbrsiege), PAR DIFFÉRENCE plutôt qu'en supprimant tout pour recréer :
     *  - les sièges conservés (numéro toujours dans le plan) sont repositionnés EN PLACE
     *    → leur id et les tickets qui y sont rattachés restent valides ;
     *  - les sièges manquants (capacité augmentée) sont ajoutés ;
     *  - les sièges en trop (capacité réduite) sont supprimés, SAUF s'ils portent un ticket
     *    actif (on bloque alors avec un message précis au lieu de tout casser).
     */
    private function synchroniserSieges(Car $car, int $identreprise): void
    {
        $siegesGauche = $car->getSiegesGauche() ?? 0;
        $siegesDroite = $car->getSiegesDroite() ?? 0;
        $nbrSiege = $car->getNbrsiege() ?? 0;

        if($nbrSiege < 0 || $siegesGauche < 0 || $siegesDroite < 0) {
            throw new BadRequestHttpException('Le nombre de sièges ne peut pas être négatif');
        }
        if($nbrSiege > 0 && ($siegesGauche + $siegesDroite) === 0) {
            throw new BadRequestHttpException('Indiquez au moins un siège à gauche ou à droite pour disposer les sièges');
        }

        $plan = $this->calculerPlan($nbrSiege, $siegesGauche, $siegesDroite); // numero => [rangee, colonne, cote]

        /** @var array<int, Siege> $existants */
        $existants = [];
        foreach($car->getSieges() as $siege) {
            $existants[$siege->getNumero()] = $siege;
        }

        // Ajout / repositionnement
        foreach($plan as $numero => [$rangee, $colonne, $cote]) {
            if(isset($existants[$numero])) {
                $existants[$numero] /* - On déplace le siège existant : id + tickets conservés */
                    ->setRangee($rangee)
                    ->setColonne($colonne)
                    ->setCote($cote);
            } else {
                $siege = new Siege();
                $siege
                    ->setNumero($numero)
                    ->setRangee($rangee)
                    ->setColonne($colonne)
                    ->setCote($cote)
                    ->setCar($car)
                    ->setIdentreprise($identreprise);
                $this->em->persist($siege);
                $car->addSiege($siege);
            }
        }

        // Suppression des sièges hors plan (capacité réduite) — interdite si vendu
        foreach($existants as $numero => $siege) {
            if(!isset($plan[$numero])) {
                if($this->siegeAUnTicketActif($siege)) {
                    throw new BadRequestHttpException(sprintf(
                        'Impossible de retirer le siège n°%d : un ticket actif y est rattaché. Annulez d\'abord ce ticket.',
                        $numero
                    ));
                }
                $car->getSieges()->removeElement($siege);
                $this->em->remove($siege); // orphanRemoval : le siège est supprimé
            }
        }
    }

    /**
     * Plan théorique des sièges : numéro => [rangée, colonne, côté].
     * Côté gauche d'abord puis droite, numérotation séquentielle.
     *
     * @return array<int, array{0:int,1:int,2:string}>
     */
    private function calculerPlan(int $nbrSiege, int $siegesGauche, int $siegesDroite): array
    {
        $plan = [];
        if($nbrSiege <= 0 || ($siegesGauche + $siegesDroite) === 0) {
            return $plan;
        }
        $numero = 1;
        $rangee = 1;
        while($numero <= $nbrSiege) {
            for($col = 1; $col <= $siegesGauche && $numero <= $nbrSiege; $col++) {
                $plan[$numero++] = [$rangee, $col, 'GAUCHE'];
            }
            for($col = 1; $col <= $siegesDroite && $numero <= $nbrSiege; $col++) {
                $plan[$numero++] = [$rangee, $col, 'DROITE'];
            }
            $rangee++;
        }
        return $plan;
    }

    private function siegeAUnTicketActif(Siege $siege): bool
    {
        if($siege->getId() === null) {
            return false;
        }
        return null !== $this->em->getRepository(Ticket::class)->findOneBy([
            'siege' => $siege,
            'deletedAt' => null,
        ]);
    }

    /**
     * Resynchronise la capacité (placestotal) des voyages NON clôturés utilisant ce car
     * quand son nombre de sièges change. Les voyages clôturés (datearriveereelle renseigné) ne sont
     * jamais modifiés ; placesoccupees (tickets vendus) n'est pas touché.
     */
    private function synchroniserPlacestotalVoyages(Car $car, int $entrepriseId): void
    {
        if($car->getId() === null) {
            return; // car en cours de création : aucun voyage rattaché
        }
        $voyages = $this->em->getRepository(Voyage::class)->findBy([
            'car' => $car,
            'identreprise' => $entrepriseId,
            'datearriveereelle' => null,
            'deletedAt' => null,
        ]);
        foreach($voyages as $voyage) {
            $voyage->setPlacesTotal($car->getNbrsiege());
        }
    }
}
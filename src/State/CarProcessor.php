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
        $nbrSiege = $car->getNbrsiege() ?? 0;
        if($nbrSiege < 0) {
            throw new BadRequestHttpException('Le nombre de sièges ne peut pas être négatif');
        }

        // MODÈLE UNIQUE : la GRILLE (plansieges). Si aucune n'est fournie, on en génère une STANDARD depuis
        // gauche/droite et on la MATÉRIALISE → le car finit toujours avec une grille explicite (source unique,
        // éditable). Les dispositions particulières (droite d'abord, banquette, bus atypiques) se saisissent
        // directement en plan explicite (via le générateur du formulaire).
        $grille = $car->getPlansieges();
        if(empty($grille)) {
            $grille = $this->grilleStandard($nbrSiege, $car->getSiegesGauche() ?? 0, $car->getSiegesDroite() ?? 0);
            $car->setPlansieges($grille);
        }

        $plan = $this->calculerPlanDepuisMap($grille); // numero => [rangee, colonne, 'GRILLE']
        $car->setNbrsiege(count($plan)); // le nombre de sièges est DÉRIVÉ de la grille

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
     * Grille STANDARD par défaut (gauche | allée | droite), numérotée gauche→droite rangée par rangée.
     * Utilisée quand aucun plan explicite n'est fourni. Renvoie une grille au même format que plansieges
     * (rangées de cellules : numéro ou null = allée) → matérialisée sur le car.
     *
     * @return array<int, array<int, ?int>>
     */
    private function grilleStandard(int $nbrSiege, int $siegesGauche, int $siegesDroite): array
    {
        if($nbrSiege <= 0) {
            return [];
        }
        // Aucune colonne définie : repli sur une seule rangée pleine largeur.
        if(($siegesGauche + $siegesDroite) === 0) {
            return [range(1, $nbrSiege)];
        }

        $grille = [];
        $numero = 1;
        $allee = ($siegesGauche > 0 && $siegesDroite > 0);
        while($numero <= $nbrSiege) {
            $rangee = [];
            for($c = 1; $c <= $siegesGauche && $numero <= $nbrSiege; $c++) {
                $rangee[] = $numero++;
            }
            if($allee) {
                $rangee[] = null; // allée centrale
            }
            for($c = 1; $c <= $siegesDroite && $numero <= $nbrSiege; $c++) {
                $rangee[] = $numero++;
            }
            $grille[] = $rangee;
        }

        return $grille;
    }

    /**
     * Plan à partir d'une GRILLE explicite (Phase 2) : tableau de rangées, cellules = numéro de siège ou
     * null/0 (trou/allée). rangée = index de ligne (1-based), colonne = index de colonne ABSOLU (1-based),
     * côté 'GRILLE'. Gère n'importe quelle disposition (siège chauffeur, portes, rangées mixtes, PMR…).
     *
     * @param array<int, mixed> $grille
     * @return array<int, array{0:int,1:int,2:string}>
     */
    private function calculerPlanDepuisMap(array $grille): array
    {
        $plan = [];
        $rangee = 0;
        foreach($grille as $ligne) {
            $rangee++;
            if(!is_array($ligne)) {
                continue;
            }
            $colonne = 0;
            foreach($ligne as $cellule) {
                $colonne++;
                $numero = (int) $cellule;
                if($numero <= 0) {
                    continue; // trou / allée
                }
                if(isset($plan[$numero])) {
                    throw new BadRequestHttpException(sprintf('Le siège n°%d apparaît plusieurs fois dans le plan.', $numero));
                }
                $plan[$numero] = [$rangee, $colonne, 'GRILLE'];
            }
        }
        if(empty($plan)) {
            throw new BadRequestHttpException('Le plan de sièges est vide : indiquez au moins un numéro de siège.');
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
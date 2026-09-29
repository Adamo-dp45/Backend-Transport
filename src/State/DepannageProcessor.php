<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\DepannageStatus;
use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CarStatutService;
use App\Domain\Service\StockmouvementService;
use App\Entity\Depannage;
use App\Entity\Detaildepannage;
use App\Entity\Detailmaindoeuvre;
use App\Entity\Dto\DepannageInput;
use App\Entity\User;
use App\Repository\CarRepository;
use App\Repository\DepannageRepository;
use App\Repository\DetaildepannageRepository;
use App\Repository\DetailmaindoeuvreRepository;
use App\Repository\PieceRepository;
use App\Repository\TypepanneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DepannageProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private CarRepository $carRepository,
        private PieceRepository $pieceRepository,
        private EntityManagerInterface $em,
        private StockmouvementService $stockmouvementService,
        private DepannageRepository $depannageRepository,
        private TypepanneRepository $typepanneRepository,
        private CarStatutService $carStatutService,
        private ActiviteLogger $activiteLogger,
        private DetaildepannageRepository $detaildepannageRepository,
        private DetailmaindoeuvreRepository $detailmaindoeuvreRepository
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var DepannageInput $data */

        /**
         * @var User
         */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if($operation instanceof Post) {
            return $this->handlePost($data, $user->getId(), $entrepriseId, $operation, $uriVariables, $context);
        }

        if($operation instanceof Patch) {
            return $this->handlePatch($data, $user->getId(), $entrepriseId, $operation, $uriVariables, $context);
        }
    }

    private function handlePost($data, $userId, $entrepriseId, $operation, $uriVariables, $context)
    {
        /** @var DepannageInput $data */

        $car = $this->getCar($data->car, $entrepriseId);
        $typepanne = $this->getTypepanne($data->typepanne, $entrepriseId);
        $this->carStatutService->verifierDisponibiliteDepannage($car);
        $depannage = new Depannage();
        $depannage
            ->setLieudepannage($data->lieudepannage)
            ->setDescription($data->description)
            ->setIdentreprise($entrepriseId)
            ->setCar($car)
            ->setTypepanne($typepanne)
            ->setCreatedBy($userId)
            ->setDatedepannage(new \DateTimeImmutable()) // Ou le reçevoir via le 'input'
        ;
        $this->carStatutService->mettreEnPanne($car); // Pour indiquer que le car est en panne
        $this->em->persist($depannage);
        $this->em->flush(); /*
            - Va être nécessaire pour avoir l'id vu qu'on utilise un 'input'
        */
        $totalPieces = $this->handleDetails($depannage, $data->details, $entrepriseId, $userId);
        $totalMaindoeuvre = $this->handleMaindoeuvres($depannage, $data->maindoeuvres ?? []);
        $depannage->setCouttotal($totalPieces + $totalMaindoeuvre);

        // Journal : le car passe EN PANNE — il sort de l'exploitation, c'est une immobilisation.
        $this->activiteLogger->log(
            ActiviteLogger::DEPANNAGE_OUVERT,
            sprintf(
                'Dépannage ouvert sur le car %s (%s) à %s — véhicule immobilisé',
                $car->getMatricule() ?? '—',
                $typepanne?->getLibelle() ?? 'panne non typée',
                $data->lieudepannage ?: '—'
            ),
            'Depannage',
            $depannage->getId()
        );

        return $this->processor->process($depannage, $operation, $uriVariables, $context);
    }

    private function handlePatch($data, $userId, $entrepriseId, $operation, $uriVariables, $context)
    {
        /**
         * @var Depannage
         */
        $depannage = $this->depannageRepository->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);

        if(!$depannage) {
            throw new NotFoundHttpException('Dépannage invalide');
        }

        if($depannage->getStatut() === DepannageStatus::CLOTURE->value) {
            throw new BadRequestHttpException('Dépannage clôturé non modifiable');
        }
        /*
            if(isset($data->car)) {
                $car = $this->getCar($data->car, $entrepriseId);
                $depannage->setCar($car);
            }
        */
        if(isset($data->car)) {
            $newCar = $this->getCar($data->car, $entrepriseId);
            $oldCar = $depannage->getCar();
            if($oldCar->getId() !== $newCar->getId()) {
                $this->carStatutService->verifierDisponibiliteDepannage($newCar);
                $this->carStatutService->mettreDisponible($oldCar); /*
                    - L'ancien car redevient disponible et le nouveau en panne
                */
                $this->carStatutService->mettreEnPanne($newCar);
            }
            $depannage->setCar($newCar);
        }

        if(isset($data->typepanne)) {
            $typepanne = $this->getTypepanne($data->typepanne, $entrepriseId);
            $depannage->setTypepanne($typepanne);
        }

        $depannage
            ->setLieudepannage($data->lieudepannage ?? $depannage->getLieudepannage())
            ->setDescription($data->description ?? $depannage->getDescription())
            ->setUpdatedBy($userId);

        /*
            LE COÛT TOTAL SE RECOMPOSE DES DEUX CÔTÉS, toujours. Chaque gestionnaire rend son total, et
            celui qu'on n'a pas touché est relu en base — ses lignes sont inchangées, la base en est
            donc la source. Sans cela, corriger une pièce remettrait 'couttotal' à « pièces seules »
            et EFFACERAIT la main d'œuvre du chiffre, silencieusement.
        */
        $idDepannage = (int) $depannage->getId();

        $totalPieces = !empty($data->details)
            ? $this->reconcileDetails($depannage, $data->details, $entrepriseId, $userId) /*
                - Réconciliation par différence : on ne touche au stock que pour ce qui change réellement
              */
            : $this->detaildepannageRepository->totalPourDepannage($idDepannage);

        // 'null' = « je ne touche pas à la main d'œuvre » ; '[]' = « supprime-la ». Cf. 'DepannageInput'.
        $totalMaindoeuvre = $data->maindoeuvres !== null
            ? $this->reconcileMaindoeuvres($depannage, $data->maindoeuvres)
            : $this->detailmaindoeuvreRepository->totalPourDepannage($idDepannage);

        $depannage->setCouttotal($totalPieces + $totalMaindoeuvre);

        return $this->processor->process($depannage, $operation, $uriVariables, $context);
    }

    /**
     * Création des lignes de MAIN D'ŒUVRE EXTERNE, et leur total.
     *
     * Aucun mouvement de stock, aucune réservation : une prestation ne consomme rien de l'inventaire.
     * C'est toute la différence avec les pièces, et c'est pourquoi la réconciliation est ici triviale.
     *
     * @param list<array<string, mixed>> $lignes
     */
    private function handleMaindoeuvres(Depannage $depannage, array $lignes): int
    {
        $total = 0;

        foreach($lignes as $ligne) {
            $montant = (int) ($ligne['montant'] ?? 0);
            if($montant <= 0) {
                throw new BadRequestHttpException("Le montant d'une main d'œuvre doit être supérieur à zéro");
            }

            $intervenant = trim((string) ($ligne['intervenant'] ?? ''));
            if($intervenant === '') {
                throw new BadRequestHttpException("L'intervenant d'une main d'œuvre est obligatoire");
            }

            $detail = (new Detailmaindoeuvre())
                ->setDepannage($depannage)
                ->setIntervenant($intervenant)
                ->setPrestation(isset($ligne['prestation']) ? trim((string) $ligne['prestation']) : null)
                ->setMontant($montant);

            $this->em->persist($detail);
            $total += $montant;
        }

        return $total;
    }

    /**
     * Remplace la main d'œuvre d'un dépannage par celle reçue, et rend son total.
     *
     * ON EFFACE ET ON RÉÉCRIT, contrairement aux pièces qui sont réconciliées ligne à ligne. La raison
     * est le STOCK : une pièce retirée doit y retourner et une quantité augmentée en sortir, ce qui
     * interdit de tout jeter. Une prestation ne touche à rien : la réécriture est exacte et se lit d'un
     * coup d'œil, là où une réconciliation par clé demanderait un identifiant stable que le formulaire
     * n'a pas.
     *
     * @param list<array<string, mixed>> $lignes
     */
    private function reconcileMaindoeuvres(Depannage $depannage, array $lignes): int
    {
        foreach($this->detailmaindoeuvreRepository->findPourDepannage((int) $depannage->getId()) as $existant) {
            $this->em->remove($existant);
        }

        return $this->handleMaindoeuvres($depannage, $lignes);
    }

    /**
     * Réconcilie les détails lors d'une modification (clé = la pièce) en ne
     * générant QUE les mouvements de stock nécessaires (un dépannage = SORTIE) :
     *  - pièce retirée      → ENTREE de sa quantité (on remet la pièce en stock)
     *  - pièce ajoutée      → SORTIE de sa quantité
     *  - quantité augmentée → SORTIE du surplus uniquement (on consomme plus)
     *  - quantité diminuée  → ENTREE de la différence (on rend au stock)
     *  - pièce inchangée    → AUCUN mouvement
     * Les lignes conservées sont mises à jour en place (pas de suppression/recréation).
     */
    private function reconcileDetails(Depannage $depannage, $details, $entrepriseId, $userId): int
    {
        $ids = array_map(fn($d) => $d['piece'], $details);
        if(count($ids) !== count(array_unique($ids))) {
            throw new BadRequestHttpException('Une pièce est en doublon dans ce dépannage');
        }

        /** @var array<int, Detaildepannage> $existants */
        $existants = [];
        foreach($depannage->getDetaildepannages() as $detail) {
            $existants[$detail->getPiece()->getId()] = $detail;
        }

        $piecesRecues = [];
        $total = 0;

        foreach($details as $detailInput) {
            $pieceId = (int)$detailInput['piece'];
            $piecesRecues[$pieceId] = true;

            $quantite = (int)$detailInput['quantite'];
            if($quantite <= 0) {
                throw new BadRequestHttpException('Quantité invalide');
            }

            if(isset($existants[$pieceId])) { /*
                - Pièce déjà présente : on n'ajuste que le delta de quantité
            */
                $detail = $existants[$pieceId];
                $prixunitaire = (int)($detailInput['prixunitaire'] ?? $detail->getPrixunitaire());
                if($prixunitaire <= 0) {
                    throw new BadRequestHttpException('Prix unitaire invalide');
                }
                $delta = $quantite - $detail->getQuantite();
                if($delta > 0) {
                    $this->stockmouvementService->createMovement(
                        $detail->getPiece(),
                        Typemouvement::SORTIE->value,
                        $delta,
                        Referencetype::DEPANNAGE->value,
                        $depannage->getId(),
                        $entrepriseId,
                        $userId
                    );
                } elseif($delta < 0) {
                    $this->stockmouvementService->createMovement(
                        $detail->getPiece(),
                        Typemouvement::ENTREE->value,
                        -$delta,
                        Referencetype::DEPANNAGE->value,
                        $depannage->getId(),
                        $entrepriseId,
                        $userId
                    );
                }
                $detail
                    ->setQuantite($quantite)
                    ->setPrixunitaire($prixunitaire);
            } else { /*
                - Nouvelle pièce : sortie complète
            */
                $piece = $this->pieceRepository->findOneBy([
                    'id' => $pieceId,
                    'identreprise' => $entrepriseId,
                    'deletedAt' => null
                ]);
                if(!$piece) {
                    throw new NotFoundHttpException('Pièce invalide');
                }
                $prixunitaire = (int)($detailInput['prixunitaire'] ?? $piece->getPrixunitaire());
                if($prixunitaire <= 0) {
                    throw new BadRequestHttpException('Prix unitaire invalide');
                }
                $detail = new Detaildepannage();
                $detail
                    ->setPiece($piece)
                    ->setQuantite($quantite)
                    ->setDepannage($depannage)
                    ->setPrixunitaire($prixunitaire);
                $this->em->persist($detail);

                $this->stockmouvementService->createMovement(
                    $piece,
                    Typemouvement::SORTIE->value,
                    $quantite,
                    Referencetype::DEPANNAGE->value,
                    $depannage->getId(),
                    $entrepriseId,
                    $userId
                );
            }

            $total += $prixunitaire * $quantite;
        }

        foreach($existants as $pieceId => $detail) { /*
            - Pièces présentes avant mais absentes maintenant : on remet en stock
        */
            if(!isset($piecesRecues[$pieceId])) {
                $this->stockmouvementService->createMovement(
                    $detail->getPiece(),
                    Typemouvement::ENTREE->value,
                    $detail->getQuantite(),
                    Referencetype::DEPANNAGE->value,
                    $depannage->getId(),
                    $entrepriseId,
                    $userId
                );
                $this->em->remove($detail);
            }
        }

        return $total;
    }

    private function handleDetails(Depannage $depannage, $details, $entrepriseId, $userId)
    {
        $ids = array_map(fn($d) => $d['piece'], $details);
        if(count($ids) !== count(array_unique($ids))) {
            throw new BadRequestHttpException('Une pièce est en doublon dans ce dépannage');
        }

        $total = 0;
        foreach($details as $detailInput) {
            $piece = $this->pieceRepository->findOneBy([
                'id' => $detailInput['piece'],
                'identreprise' => $entrepriseId,
                'deletedAt' => null
            ]);

            if(!$piece) {
                throw new NotFoundHttpException('Pièce invalide');
            }

            $prixunitaire = $detailInput['prixunitaire'] ?? $piece->getPrixunitaire();
            $quantite = $detailInput['quantite'];

            if($quantite <= 0) { // Vu qu'on n'a de pas règle dessus
                throw new BadRequestHttpException('Quantité invalide');
            }

            if ($prixunitaire <= 0) {
                throw new BadRequestHttpException('Prix unitaire invalide');
            }

            $detail = new Detaildepannage();
            $detail
                ->setPiece($piece)
                ->setQuantite($quantite)
                ->setDepannage($depannage)
                ->setPrixunitaire($prixunitaire); /*
                    ->setCouttotal($coutTotalLigne) -- On peut '$coutTotalLigne = $prixunitaire * $quantite' si on veut le cout total par détail
                */
            $this->em->persist($detail);

            # On crée un mouvement stock
            $this->stockmouvementService->createMovement(
                $piece,
                Typemouvement::SORTIE->value,
                $quantite,
                Referencetype::DEPANNAGE->value,
                $depannage->getId(),
                $entrepriseId,
                $userId
            );

            $total += $prixunitaire * $quantite;
        }

        return $total;
    }

    private function getCar(int $carId, int $entrepriseId)
    {
        $car = $this->carRepository->findOneBy([
            'id' => $carId,
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);
        if(!$car) {
            throw new NotFoundHttpException('Car invalide');
        }
        return $car;
    }

    private function getTypepanne(int $typepanneId, int $entrepriseId)
    {
        $typepanne = $this->typepanneRepository->findOneBy([
            'id' => $typepanneId,
            'identreprise' => $entrepriseId,
            'deletedAt' => null
        ]);
        if(!$typepanne) {
            throw new NotFoundHttpException('Type de panne invalide');
        }
        return $typepanne;
    }
}

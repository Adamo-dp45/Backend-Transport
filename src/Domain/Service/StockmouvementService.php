<?php

namespace App\Domain\Service;

use App\Domain\Enum\Typemouvement;
use App\Entity\Inventaire;
use App\Entity\Piece;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StockmouvementService
{
    public function __construct(
        private EntityManagerInterface $em
    )
    {
    }

    public function createMovement(
        Piece $piece,
        string $type, // Au lieu de 'Typemouvement'
        int $quantite,
        string $referencetype, // !! 'Referencetype'
        ?int $referenceId,
        int $entrepriseId,
        int $createdBy
    ): void
    {
        if($type === Typemouvement::SORTIE->value && $piece->getStockinitial() < $quantite) {
            throw new BadRequestHttpException(sprintf(
                'Stock insuffisant pour la pièce %s (Stock: %d, demandé: %d)',
                $piece->getLibelle(),
                $piece->getStockinitial(),
                $quantite
            ));
        }

        if($piece->getStockinitial() <= $piece->getSeuilstock()) { /*
                - Un évènement pour avertir que le stock est faible ou envoyer un email
            */
        }

        $movement = new Inventaire();
        $movement
            ->setPiece($piece)
            ->setTypemouvement($type)
            ->setQuantite($quantite)
            ->setReferenceType($referencetype)
            ->setReferenceid($referenceId)
            ->setIdentreprise($entrepriseId)
            ->setDatemouvement(new \DateTimeImmutable())
            ->setCreatedBy($createdBy)
            // Auteur en relation (référence légère : pas de requête de chargement)
            ->setAuteur($createdBy ? $this->em->getReference(User::class, $createdBy) : null);

        if($type === TypeMouvement::ENTREE->value) {
            $piece->setStockinitial($piece->getStockinitial() + $quantite); # On met à jour le stock
        }

        if($type === TypeMouvement::SORTIE->value) {
            $piece->setStockinitial($piece->getStockinitial() - $quantite); # !!
        }

        $this->em->persist($movement); /*
            $this->em->flush(); -- Vu que le '->process()' le fait
        */
    }
}
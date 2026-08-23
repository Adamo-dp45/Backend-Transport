<?php

namespace App\DataFixtures;

use App\Domain\Enum\Referencetype;
use App\Domain\Enum\Typemouvement;
use App\Entity\Inventaire;
use App\Entity\Piece;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Persistence\ObjectManager;

/**
 * Écriture d'un mouvement de stock, calquée sur 'StockmouvementService::createMovement()'.
 *
 * Le registre 'Inventaire' et le stock de la pièce vont TOUJOURS ensemble : écrire l'un sans l'autre
 * fait diverger l'inventaire du stock affiché, et les alertes de stock deviennent mensongères.
 * Approvisionnements (entrées) et dépannages (sorties) passent donc tous les deux par ici.
 *
 * Requiert 'HorodatageTrait' : le mouvement est antidaté à sa date réelle.
 */
trait MouvementStockTrait
{
    private function enregistrerMouvement(
        ObjectManager $manager,
        Piece $piece,
        Typemouvement $type,
        int $quantite,
        Referencetype $referenceType,
        ?int $referenceId,
        int $identreprise,
        User $auteur,
        DateTimeImmutable $date
    ): void {
        $mouvement = (new Inventaire())
            ->setPiece($piece)
            ->setTypemouvement($type->value)
            ->setQuantite($quantite)
            ->setDatemouvement($date)
            // Un AJUSTEMENT n'est pas un type de mouvement : il reste une ENTREE ou une SORTIE selon
            // le signe, et c'est la RÉFÉRENCE qui dit d'où il vient (cf. 'AjustementstockProcessor').
            ->setReferenceType($referenceType->value)
            ->setReferenceid($referenceId)
            ->setAuteur($auteur)
            ->setIdentreprise($identreprise);
        $mouvement->setCreatedBy($auteur->getId());

        $piece->setStockinitial(match ($type) {
            Typemouvement::ENTREE => $piece->getStockinitial() + $quantite,
            Typemouvement::SORTIE => $piece->getStockinitial() - $quantite,
            Typemouvement::AJUSTEMENT => $piece->getStockinitial(),
        });

        $manager->persist($this->daterA($mouvement, $date));
    }
}

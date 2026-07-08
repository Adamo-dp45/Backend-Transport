<?php

namespace App\EventSubscriber;

use App\Domain\Enum\BagageStatus;
use App\Domain\Enum\CourrierStatus;
use App\Entity\Bagage;
use App\Entity\Courrier;
use App\Entity\Voyage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postUpdate)] /*
    - Le 'postUpdate' au lieu de 'preUpdate' car avec lui le flush est encore en cours et les 'persist' sur d'autres entités sont ignorés ou causent des comportements imprévisibles or en 'postUpdate' le flush principal est terminé et on peut en déclencher un nouveau proprement
*/
class VoyageClotureStautSubscriber
{
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if(!$entity instanceof Voyage) {
            return;
        }
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork(); /*
            - En 'postUpdate' on ne peut plus utiliser 'hasChangedField', on récupère le 'changeset' depuis le 'UnitOfWork'
        */
        $changeset = $uow->getEntityChangeSet($entity);
        if(!isset($changeset['datearriveereelle'])) {
            return;
        }
        [$oldValue, $newValue] = $changeset['datearriveereelle']; /*
            - datearriveereelle passe de null à une valeur → voyage vient d'être clôturé
        */
        if($oldValue !== null || $newValue === null) {
            return;
        }
        $hasChanges = false;

        $bagages = $em->getRepository(Bagage::class)->findBy([
            'voyage' => $entity,
            'deletedAt' => null
        ]);
        foreach($bagages as $bagage) {
            if(!in_array($bagage->getStatut(), [
                BagageStatus::STATUT_LIVRE->value,
                BagageStatus::STATUT_PERDU->value
            ])) {
                $bagage->setStatut(BagageStatus::STATUT_LIVRE->value);
                $em->persist($bagage);
                $hasChanges = true;
            }
        }

        $courriers = $em->getRepository(Courrier::class)->findBy([
            'voyage' => $entity,
            'deletedAt' => null
        ]);
        $terminus = $entity->getLigne()?->getGareterminus(); /*
            - Réception « à l'arrêt » : la clôture (= terminus atteint) n'auto-réceptionne que les colis
              destinés au terminus. Les colis destinés à un arrêt intermédiaire sont réceptionnés manuellement
              à leur gare via l'endpoint '/courriers/{id}/receptionner'.
        */
        foreach($courriers as $courrier) { /*
            - On ne touche pas aux courriers déjà livrés ou annulés ni à ceux forcés manuellement au-delà de 'RECEPTIONNE'
        */
            // EN_ATTENTE inclus : un voyage DIRECT (origine -> terminus) clôturé sans avoir été « démarré »
            // (donc sans passage EN_ATTENTE -> EN_TRANSIT) arrive quand même ; ses colis destinés au
            // terminus doivent être réceptionnés.
            if(!in_array($courrier->getStatut(), [
                CourrierStatus::STATUT_EN_ATTENTE->value,
                CourrierStatus::STATUT_EN_TRANSIT->value,
            ], true)) {
                continue;
            }
            $arrivee = $courrier->getGarearrivee();
            $destineAuTerminus = $terminus === null   // voyage sans ligne (legacy) -> comportement historique
                || $arrivee === null                  // colis sans gare d'arrivée (legacy)
                || $arrivee->getId() === $terminus->getId();
            if($destineAuTerminus) {
                $courrier->setStatut(CourrierStatus::STATUT_RECEPTIONNE->value);
                $em->persist($courrier);
                $hasChanges = true;
            }
        }

        if($hasChanges) {
            $em->flush(); /*
                - On n'a pas séparer en 2 subscriber 'CourrierStatutSubscriber' et 'BagageStatutSubscriber' car pn aura un problème si 2 subscribers font un 'flush()' à l'intérieur d'un 'postUpdate' sur la même entité vu que le 1er subscriber qui exécute son 'flush()' modifie l'état interne du 'UnitOfWork', le second subscriber travaille ensuite avec un 'changeset' déjà consommé ou réinitialisé donc ne vas appliquer ses modifications
            */
        }
    }
}

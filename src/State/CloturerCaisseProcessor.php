<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Enum\SessioncaisseStatut;
use App\Domain\Service\ActiviteLogger;
use App\Domain\Service\CaisseTheoriqueService;
use App\Entity\Dto\ClotureCaisseInput;
use App\Entity\Sessioncaisse;
use App\Entity\User;
use App\Security\CaisseGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * LA CLÔTURE : l'agent compte son tiroir, le serveur calcule ce qu'il aurait dû contenir, l'écart
 * est constaté et motivé. C'est le geste qui rend un manquant opposable — jusqu'ici il ne se
 * détectait que par recoupement manuel, a posteriori, et sans rien à présenter à l'agent.
 *
 * !! DÉFINITIVE. Deux statuts, pas trois, et aucune réouverture : rouvrir permettrait de récrire un
 * constat déjà signé. Si l'agent reprend la vente, sa première écriture lui ouvre une NOUVELLE
 * session ('SessioncaisseService') et cette clôture-ci reste intacte.
 */
class CloturerCaisseProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private CaisseTheoriqueService $caisseTheoriqueService,
        private EntityManagerInterface $em,
        private Security $security,
        private CaisseGuard $caisseGuard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var ClotureCaisseInput $data */
        $session = $this->em->getRepository(Sessioncaisse::class)->find($uriVariables['id'] ?? 0);

        if ($session === null) {
            throw new NotFoundHttpException('Caisse introuvable');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        /*
            LA PERMISSION DIT « il sait clôturer », CETTE GARDE DIT « celle-ci ». 'CLOTURER' est une
            permission d'ENTITÉ : sans ce second test, un guichetier habilité fermerait la caisse du
            collègue d'à côté, en figeant des totaux qu'il n'a pas comptés.
        */
        $this->caisseGuard->assertPeutCloturer($user, $session);

        if ($session->getStatut() !== SessioncaisseStatut::OUVERTE->value) {
            throw new BadRequestHttpException('Cette caisse est déjà clôturée : une clôture ne se refait pas.');
        }

        /*
            GEL D'ABORD, écart ensuite : l'écart se lit contre le théorique qui vient d'être FIGÉ sur
            la session, jamais contre un calcul refait à la volée. Sans quoi une annulation tardive
            déplacerait demain le chiffre auquel l'agent a été confronté ce soir.
        */
        $this->caisseTheoriqueService->geler($session);

        $compte = (int) $data->montantcompte;
        $ecart = $compte - (int) $session->getMontanttheorique();

        /*
            LE MOTIF EST OBLIGATOIRE DÈS QUE L'ÉCART N'EST PAS NUL, dans les DEUX SENS. Un excédent
            s'explique aussi mal qu'un manquant : il signale une monnaie mal rendue, ou une vente
            encaissée sans être saisie — c'est-à-dire, justement, ce qu'on cherche. Ne garder la
            garde que sur le négatif reviendrait à fermer les yeux sur la moitié des cas.
        */
        if ($ecart !== 0 && trim((string) $data->motifecart) === '') {
            /*
                !! LE MESSAGE NE DIT PAS LE MONTANT DE L'ÉCART, et ce n'est pas une omission. Le
                comptage est AVEUGLE : annoncer « il vous manque 1 000 » à un agent qui peut encore
                corriger sa saisie lui offre de faire tomber le compte juste et de repartir sans
                motif. Il apprend qu'il y a un écart — il doit bien pouvoir le justifier — mais il
                le découvre CHIFFRÉ une fois la clôture faite, quand elle n'est plus modifiable.

                LIMITE CONNUE : rien ne consigne les tentatives refusées, donc un agent peut encore
                tâtonner pour trouver le montant qui passe. La fermer demanderait de garder trace de
                chaque saisie rejetée — un autre chantier.
            */
            throw new BadRequestHttpException(
                'Le compte ne tombe pas juste : indiquez un motif pour clôturer votre caisse.'
            );
        }

        $session
            ->setMontantcompte($compte)
            ->setEcart($ecart)
            ->setMotifecart($ecart !== 0 ? trim((string) $data->motifecart) : null)
            ->setDatefin(new \DateTimeImmutable())
            ->setStatut(SessioncaisseStatut::CLOTUREE->value)
            /*
                LA COLONNE DE REPLI RETOMBE À NULL : c'est elle qui porte l'index unique
                ('identreprise', 'agentsessionouverte'). Sans cette ligne, l'agent ne pourrait plus
                JAMAIS ouvrir de caisse — la contrainte refuserait la suivante, et le refus
                arriverait sous forme de violation d'index au beau milieu d'une vente.
            */
            ->setAgentsessionouverte(null)
            ->setUpdatedBy($user->getId());

        $resultat = $this->processor->process($session, $operation, $uriVariables, $context);

        $this->activiteLogger->log(
            ActiviteLogger::CAISSE_CLOTUREE,
            sprintf(
                'Caisse de %s clôturée : %d FCFA comptés pour %d attendus (écart %+d)%s',
                trim(($session->getAgent()?->getPrenom() ?? '') . ' ' . ($session->getAgent()?->getNom() ?? '')),
                $compte,
                (int) $session->getMontanttheorique(),
                $ecart,
                $ecart !== 0 ? ' — ' . $session->getMotifecart() : ''
            ),
            'Sessioncaisse',
            $session->getId()
        );

        return $resultat;
    }
}

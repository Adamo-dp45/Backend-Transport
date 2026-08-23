<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ClientResolver;
use App\Entity\Ticket;
use App\Entity\User;
use App\Security\GareGuard;
use App\Security\VoyageGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Modification d'un billet (PATCH /tickets/{id}) — remplace l'ancien passage par 'UpdatedbyProcessor',
 * trop générique pour porter la logique métier du billet.
 *
 * Seule l'IDENTITÉ du passager est modifiable ('write:Ticket:update' = nomclient + contactclient) :
 * ni tronçon, ni siège, ni prix (qui exigeraient de rejouer capacité/tarification — c'est le rôle du
 * 'TicketProcessor' à la VENTE, volontairement non réutilisé ici pour ne pas re-déclencher tout le
 * flux de création sur un simple changement de nom).
 *
 * Gardes (du plus large au plus fin) :
 *  1. voyage CLÔTURÉ → figé ;
 *  2. gare ÉMETTRICE seule habilitée (un agent d'une autre gare ne touche pas au billet) — OU le
 *     COMMERCIAL qui l'a vendu à bord, tant que le car est ENCORE à la gare de montée du billet
 *     (sa position courante), la gare de montée n'étant pas sa gare d'attache ;
 *  3. car ayant déjà ATTEINT la gare de montée → figé pour l'agent de gare (même garde que le
 *     désistement, cf. VoyageGuard::monteeAtteinte) ; le commercial, lui, est borné par sa position
 *     courante en (2). Service en cours/rendu = on ne réécrit plus l'identité.
 *
 * Et surtout : le téléphone étant la CLÉ d'identité client, tout changement de 'contactclient' doit
 * RE-RÉSOUDRE le Client rattaché (find-or-create), sinon le snapshot change mais la FK 'client'
 * reste sur l'ancien client — incohérence silencieuse (fidélité, historique, fiche client).
 */
class TicketUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private GareGuard $gareGuard,
        private VoyageGuard $voyageGuard,
        private ClientResolver $clientResolver
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof Ticket) {
            return $this->processor->process($data, $operation, $uriVariables, $context);
        }

        /** @var User $user */
        $user = $this->security->getUser();
        $voyage = $data->getVoyage();

        if ($voyage?->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Le voyage de ce billet est clôturé : modification impossible');
        }

        /*
            Le COMMERCIAL (vendeur à bord) ne corrige QUE SES PROPRES VENTES, tant que le car est
            encore à la gare de montée du billet (= sa position courante) : mêmes règles que la vente
            et la libération de siège (TicketProcessor, DescendreTicketProcessor), la gare de montée
            du billet n'étant PAS sa gare d'attache.

            On teste le VENDEUR DU BILLET, pas le commercial du voyage : être affecté à un départ
            n'ouvre pas les billets émis au guichet par les gares qu'il dessert. 'Ticket::commercial'
            n'est renseigné que sur les ventes à bord (TicketProcessor), il porte donc exactement
            cette distinction.

            CONSÉQUENCE ASSUMÉE : un billet vendu au guichet n'est plus corrigible par personne une
            fois le car parti de sa gare de montée — ni par la gare émettrice, ni par un admin, ni
            par le commercial. La correction doit se faire avant le départ.

            Sinon (agent de gare) : gare émettrice + car pas encore passé.
        */
        $estCommercial = $data->getCommercial()?->getId() === $user->getId();
        if ($estCommercial) {
            if (!$this->voyageGuard->surLaGareDeMontee($voyage, $data->getGare())) {
                throw new BadRequestHttpException('Le car a quitté la gare de montée de ce billet : il n\'est plus modifiable.');
            }
        } else {
            $this->gareGuard->assertEstGare($user, $data->getGare(), 'Seule la gare émettrice peut modifier ce ticket');

            if ($voyage !== null) {
                // Même borne que la VENTE et que le commercial ci-dessus : tant que le car est à
                // quai, la gare qui a émis le billet peut encore le corriger. C'est seulement son
                // DÉPART qui ferme la correction.
                $this->voyageGuard->assertMonteeNonDepassee(
                    $voyage,
                    $data->getGare(),
                    'Le car a quitté la gare de montée de ce billet : il n\'est plus modifiable.'
                );
            }
        }

        // Le téléphone a pu changer → on re-rattache le billet à la bonne identité client.
        // Non-cassant : nomclient/contactclient restent le snapshot ; null si plus de téléphone exploitable.
        // On garde l'entreprise DU BILLET (repli sur celle de l'agent) : sans elle, on créerait un
        // client hors périmètre au lieu de dédupliquer.
        $entrepriseId = $data->getIdentreprise() ?? $user->getEntreprise()?->getId();
        if ($entrepriseId !== null) {
            $data->setClient(
                $this->clientResolver->resolve(
                    $data->getNomclient(),
                    $data->getContactclient(),
                    $entrepriseId,
                    $user->getId()
                )
            );
        }

        $data->setUpdatedBy($user->getId());

        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}

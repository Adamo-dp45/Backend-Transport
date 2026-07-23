<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Dto\AvancerCommercialInput;
use App\Entity\User;
use App\Entity\Voyage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Le commercial à bord fait avancer la position du car : PATCH /voyages/{id}/avancer.
 *
 * Couvre le cas d'une gare intermédiaire SANS agent (pas de réception) : le commercial, qui est
 * physiquement dans le car, déclare la gare où il se trouve désormais. Avancement MONOTONE (jamais
 * en arrière). Réservé au commercial du voyage (ou à un admin).
 */
class AvancerCommercialProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private ActiviteLogger $activiteLogger,
        private \App\Domain\Service\PassageService $passageService
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AvancerCommercialInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        if ($data->gare === null) {
            throw new BadRequestHttpException('La gare où se trouve le car est obligatoire');
        }

        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        // Réservé au commercial du voyage (ou à un admin)
        $estAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        $estCommercial = $voyage->getCommercial() && $voyage->getCommercial()->getId() === $user->getId();
        if (!$estCommercial && !$estAdmin) {
            throw new AccessDeniedHttpException('Seul le commercial du voyage peut faire avancer la position du car');
        }

        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé');
        }

        $ligne = $voyage->getLigne();
        if (!$ligne) {
            throw new BadRequestHttpException('Ce voyage n\'est rattaché à aucune ligne');
        }

        // Ordres des arrêts
        $ordreParGare = [];
        foreach ($ligne->getArrets() as $arret) {
            $ordreParGare[$arret->getGare()->getId()] = $arret->getOrdre();
        }

        $cibleId = $data->gare->getId();
        if (!isset($ordreParGare[$cibleId])) {
            throw new BadRequestHttpException('Cette gare n\'est pas un arrêt de la ligne du voyage');
        }

        // Position courante (défaut = origine EFFECTIVE : gareprovenance pour un départ partiel)
        $courante = $voyage->getGarecourante() ?? $voyage->getOrigineEffective();
        $ordreCourant = $courante ? ($ordreParGare[$courante->getId()] ?? 0) : 0;
        $ordreCible = $ordreParGare[$cibleId];

        // Avancement MONOTONE : on n'avance que vers l'aval
        if ($ordreCible <= $ordreCourant) {
            throw new BadRequestHttpException('Le car ne peut avancer que vers une gare située plus loin sur la ligne');
        }

        $voyage->setGarecourante($data->gare);

        // Passage réel : le commercial déclare que le car est ARRIVÉ à cette gare.
        $this->passageService->marquerArrivee($voyage, $data->gare, new \DateTimeImmutable());

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_POSITION,
            sprintf('Position du car avancée à %s', $data->gare->getLibelle()),
            $voyage->getId()
        );

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}

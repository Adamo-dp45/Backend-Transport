<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\ActiviteLogger;
use App\Entity\Dto\AffectCommercialInput;
use App\Entity\User;
use App\Entity\Voyage;
use App\Security\VoyageGuard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Affecte (ou retire) le commercial d'un voyage : PATCH /voyages/{id}/commercial.
 *
 * Le commercial est un User qui vend EN ROUTE depuis la position courante du car ('garecourante').
 * À l'affectation, on initialise 'garecourante' à la gare d'origine de la ligne (départ).
 */
class AffectCommercialProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $em,
        private VoyageGuard $guard,
        private ActiviteLogger $activiteLogger
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var AffectCommercialInput $data */

        /** @var User $user */
        $user = $this->security->getUser();
        $entrepriseId = $user->getEntreprise()->getId();

        $voyage = $this->em->getRepository(Voyage::class)->findOneBy([
            'id' => $uriVariables['id'],
            'identreprise' => $entrepriseId,
            'deletedAt' => null,
        ]);
        if (!$voyage) {
            throw new BadRequestHttpException('Voyage introuvable');
        }

        // Affectation du commercial = planification : réservée à la gare d'ORIGINE (+ admin/central).
        // Le commercial est un choix de départ qui appartient à la gare qui lance le voyage ; une gare
        // intermédiaire ne le change pas (elle réceptionne).
        $this->guard->assertPeutPlanifier($user, $voyage);

        if ($voyage->getDatearriveereelle() !== null) {
            throw new BadRequestHttpException('Ce voyage est clôturé : impossible de gérer le commercial');
        }

        $commercial = $data->commercial;

        // Commercial sortant (avant écrasement) : sert à nommer le changement dans le journal
        $ancien = $voyage->getCommercial();

        // Désaffectation
        if ($commercial === null) {
            $voyage->setCommercial(null);
            $this->activiteLogger->voyage(
                ActiviteLogger::VOYAGE_COMMERCIAL,
                $ancien !== null
                    ? sprintf('Commercial retiré : %s %s', $ancien->getPrenom(), $ancien->getNom())
                    : 'Commercial retiré du voyage',
                $voyage->getId()
            );
            return $this->processor->process($voyage, $operation, $uriVariables, $context);
        }

        // Affectation : le commercial doit appartenir à la même entreprise
        if ($commercial->getEntreprise()?->getId() !== $entrepriseId) {
            throw new BadRequestHttpException('Ce commercial n\'appartient pas à votre entreprise');
        }

        $voyage->setCommercial($commercial);

        // Position de départ = origine EFFECTIVE du voyage (gareprovenance pour un départ partiel)
        if ($voyage->getGarecourante() === null) {
            $origine = $voyage->getOrigineEffective();
            if ($origine !== null) {
                $voyage->setGarecourante($origine);
            }
        }

        $this->activiteLogger->voyage(
            ActiviteLogger::VOYAGE_COMMERCIAL,
            ($ancien !== null && $ancien->getId() !== $commercial->getId())
                ? sprintf(
                    'Commercial affecté : %s %s (en remplacement de %s %s)',
                    $commercial->getPrenom(), $commercial->getNom(), $ancien->getPrenom(), $ancien->getNom()
                )
                : sprintf('Commercial affecté : %s %s', $commercial->getPrenom(), $commercial->getNom()),
            $voyage->getId()
        );

        return $this->processor->process($voyage, $operation, $uriVariables, $context);
    }
}

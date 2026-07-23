<?php

namespace App\State\Public;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Service\EntreprisePubliqueResolver;
use App\Domain\Service\Paiement\PaiementProviderInterface;
use App\Domain\Service\ReservationCreationService;
use App\Entity\Dto\ReservationPubliqueInput;
use App\Entity\Output\Reservation\PaiementInfoDto;
use App\Entity\Output\Reservation\ReservationPubliqueDto;
use App\Entity\Reservation;
use App\Repository\GareRepository;
use App\Repository\VoyageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Création d'une réservation INVITÉ (mobile/web) + initiation du paiement en ligne. Réutilise le
 * moteur partagé (ReservationCreationService, source MOBILE) — mêmes règles que le guichet. Renvoie
 * un DTO public avec le bloc `paiement` (URL de redirection ou drapeau `estSimule`).
 */
final class ReservationPubliqueProcessor implements ProcessorInterface
{
    public function __construct(
        private EntreprisePubliqueResolver $resolver,
        private RequestStack $requestStack,
        private VoyageRepository $voyageRepository,
        private GareRepository $gareRepository,
        private ReservationCreationService $creationService,
        private PaiementProviderInterface $paiement,
        private EntityManagerInterface $em,
        #[Autowire(service: 'limiter.reservation_publique')]
        private RateLimiterFactory $limiter,
        private ReservationPubliqueMapper $mapper
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ReservationPubliqueDto
    {
        /** @var ReservationPubliqueInput $data */
        $request = $this->requestStack->getCurrentRequest();

        // Anti-abus : limite par IP
        if (!$this->limiter->create($request?->getClientIp() ?? 'anonymous')->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(message: 'Trop de tentatives. Réessayez dans un instant.');
        }

        $entreprise = $this->resolver->resoudre($request?->query->get('slug'));
        $entrepriseId = $entreprise->getId();

        $voyage = $this->voyageRepository->findOneBy(['id' => $data->voyage, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
        if (!$voyage) {
            throw new NotFoundHttpException('Voyage introuvable');
        }
        $montee = $this->gareRepository->findOneBy(['id' => $data->montee, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
        if (!$montee) {
            throw new NotFoundHttpException('Gare de montée introuvable');
        }
        $descente = null;
        if ($data->descente) {
            $descente = $this->gareRepository->findOneBy(['id' => $data->descente, 'identreprise' => $entrepriseId, 'deletedAt' => null]);
            if (!$descente) {
                throw new NotFoundHttpException('Gare de descente introuvable');
            }
        }

        $reservation = (new Reservation())
            ->setVoyage($voyage)
            ->setGare($montee)
            ->setGaredescente($descente)
            ->setNomclient(trim((string) $data->nom))
            ->setContactclient(trim((string) $data->contact))
            ->setSource('MOBILE');

        // Création (validation + prix verrouillé + capacité sous verrou + expiration + persist)
        $this->creationService->creer($reservation, $entrepriseId, null, null);

        // Initiation du paiement en ligne (le back ne rend aucune vue : le front redirige/simule)
        $retour = $data->returnUrl ? str_replace(['{code}', '%7Bcode%7D'], $reservation->getCode(), $data->returnUrl) : '';
        $session = $this->paiement->initier($reservation, $retour);
        $reservation->setReferencepaiement($session->reference);
        $this->em->flush();

        return $this->mapper->versDto(
            $reservation,
            new PaiementInfoDto(reference: $session->reference, url: $session->url, estSimule: $session->estSimule)
        );
    }
}

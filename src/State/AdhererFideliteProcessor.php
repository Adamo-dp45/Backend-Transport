<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Adhésion d'un client au programme de fidélité : génère un n° de carte et fixe la date d'adhésion.
 * Les tampons ne se cumulent qu'à partir de cette date (cf. FideliteService).
 */
class AdhererFideliteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $em
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Client
    {
        /** @var Client $data */
        if ($data->isFidelite()) {
            throw new BadRequestHttpException('Ce client est déjà membre du programme de fidélité');
        }

        /** @var User $user */
        $user = $this->security->getUser();

        $data
            ->setFidelite(true)
            ->setDateadhesion(new \DateTimeImmutable())
            ->setCartefidelite($data->getCartefidelite() ?? $this->genererNumeroCarte($data))
            ->setUpdatedBy($user->getId());

        $this->em->flush();

        return $data;
    }

    /**
     * N° de carte stable et lisible, unique par construction (basé sur l'id du client).
     */
    private function genererNumeroCarte(Client $client): string
    {
        return 'FID-' . date('Y') . '-' . str_pad((string) $client->getId(), 6, '0', STR_PAD_LEFT);
    }
}

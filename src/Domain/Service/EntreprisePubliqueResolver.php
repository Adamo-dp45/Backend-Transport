<?php

namespace App\Domain\Service;

use App\Domain\Enum\ReferenceStatus;
use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Résout l'entreprise à partir de son SLUG public (URL de réservation mobile/web). Utilisé par tous
 * les providers/processors de la partie PUBLIQUE (invité) : c'est ce qui remplace le scoping par
 * l'utilisateur JWT (absent côté public). Lève une 404 si le slug est inconnu ou l'entreprise suspendue.
 */
class EntreprisePubliqueResolver
{
    public function __construct(
        private EntrepriseRepository $entrepriseRepository
    )
    {
    }

    public function resoudre(?string $slug): Entreprise
    {
        $entreprise = $slug ? $this->entrepriseRepository->findOneBy(['slug' => $slug]) : null;
        if ($entreprise === null || $entreprise->getStatut() !== ReferenceStatus::ACTIF->value) {
            throw new NotFoundHttpException('Compagnie introuvable');
        }

        return $entreprise;
    }
}

<?php

namespace App\Domain\Service;

use App\Repository\EntrepriseRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Génère l'identifiant public (slug) d'une entreprise à partir de son libellé, en garantissant
 * l'UNICITÉ : si le slug existe déjà (deux compagnies au même nom), on suffixe -2, -3, … (à la
 * WordPress/GitHub). Sert d'URL publique de réservation (mobile/web-client).
 */
class SlugService
{
    public function __construct(
        private SluggerInterface $slugger,
        private EntrepriseRepository $entrepriseRepository
    )
    {
    }

    public function genererPourEntreprise(string $libelle, ?int $ignorerId = null): string
    {
        $base = $this->slugger->slug($libelle)->lower()->toString();
        if ($base === '') {
            $base = 'compagnie';
        }

        $slug = $base;
        $i = 2;
        while ($this->existe($slug, $ignorerId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function existe(string $slug, ?int $ignorerId): bool
    {
        $existant = $this->entrepriseRepository->findOneBy(['slug' => $slug]);
        return $existant !== null && $existant->getId() !== $ignorerId;
    }
}

<?php

namespace App\Tests\Support;

use App\Entity\Entreprise;
use App\Entity\Gare;
use App\Entity\Ligne;
use App\Entity\Ville;
use InvalidArgumentException;

/**
 * Le réseau minimal d'un test : une compagnie, une ligne, ses gares indexées par nom.
 *
 * Les gares sont désignées par leur NOM dans les tests ('Abidjan', 'Bouaké'…) plutôt que par des
 * variables intermédiaires : un test de capacité se lit alors comme la règle qu'il vérifie —
 * « vendre Bouaké → Korhogo ne consomme pas de place à Abidjan ».
 */
final class Reseau
{
    /**
     * @param array<string, Gare>  $gares
     * @param array<string, Ville> $villes une ville par gare, portant le même nom
     */
    public function __construct(
        public readonly Entreprise $entreprise,
        public readonly Ligne $ligne,
        private readonly array $gares,
        private readonly array $villes = [],
    ) {
    }

    /** La ville qui abrite cette gare : l'API publique liste les gares PAR ville. */
    public function ville(string $nom): Ville
    {
        return $this->villes[$nom]
            ?? throw new InvalidArgumentException(sprintf('Ville « %s » absente du réseau de test.', $nom));
    }

    public function gare(string $nom): Gare
    {
        return $this->gares[$nom]
            ?? throw new InvalidArgumentException(sprintf(
                'Gare « %s » absente du réseau de test (disponibles : %s).',
                $nom,
                implode(', ', array_keys($this->gares))
            ));
    }

    /** Position de la gare sur la ligne : c'est l'unité de raisonnement de 'CapaciteService'. */
    public function ordre(string $nom): int
    {
        $gare = $this->gare($nom);
        foreach ($this->ligne->getArrets() as $arret) {
            if ($arret->getGare() === $gare) {
                return (int) $arret->getOrdre();
            }
        }

        throw new InvalidArgumentException(sprintf('La gare « %s » n\'est pas un arrêt de la ligne.', $nom));
    }

    public function identreprise(): int
    {
        return (int) $this->entreprise->getId();
    }
}

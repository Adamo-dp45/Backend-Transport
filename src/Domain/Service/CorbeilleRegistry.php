<?php

namespace App\Domain\Service;

use App\Entity\EntityBase;
use App\Entity\Interface\EntrepriseOwnedInterface;
use Symfony\Component\Finder\Finder;

/**
 * Découvre les types « corbeillables » sans MAP à maintenir : toute entité soft-deletable (héritant
 * d'EntityBase, donc porteuse de deletedAt) ET rattachée à une entreprise (EntrepriseOwnedInterface),
 * MOINS une liste d'exclusion explicite. Le TYPE exposé est le nom court de la classe en minuscules
 * (Ticket -> « ticket »), stable et lisible pour les URLs de la corbeille.
 */
class CorbeilleRegistry
{
    /**
     * Entités volontairement HORS corbeille :
     *  - journaux / dérivés     : Activite, Alerte
     *  - lecture seule          : Inventaire
     *  - détails (cascade)      : Detailcourrier, Detailpersonnel
     *  - singletons de config   : ConfigRecette, ConfigRemise, ParametreReservation, Maintenance
     *  - dérivé / opt-in        : ProgrammeFidelite, Beneficiaire
     */
    private const EXCLUSIONS = [
        'Activite', 'Alerte', 'Inventaire',
        'Detailcourrier', 'Detailpersonnel',
        'ConfigRecette', 'ConfigRemise', 'ParametreReservation', 'Maintenance',
        'ProgrammeFidelite', 'Beneficiaire',
    ];

    private const ENTITY_NAMESPACE = 'App\\Entity\\';

    /** @var array<string, class-string>|null Cache type => FQCN */
    private ?array $map = null;

    public function __construct(
        private readonly string $entityPath
    ) {
    }

    /** @return array<string, class-string> type => classe */
    public function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];
        $finder = (new Finder())->files()->in($this->entityPath)->name('*.php')->depth(0);
        foreach ($finder as $file) {
            $short = $file->getBasename('.php');
            if (in_array($short, self::EXCLUSIONS, true)) {
                continue;
            }
            $fqcn = self::ENTITY_NAMESPACE . $short;
            if (!class_exists($fqcn)) {
                continue;
            }
            // Soft-deletable (deletedAt) ET rattachée à une entreprise (pour la colonne « Entreprise »)
            if (!is_subclass_of($fqcn, EntityBase::class) || !is_subclass_of($fqcn, EntrepriseOwnedInterface::class)) {
                continue;
            }
            $map[strtolower($short)] = $fqcn;
        }
        ksort($map);

        return $this->map = $map;
    }

    /** @return string[] Les types corbeillables, triés. */
    public function types(): array
    {
        return array_keys($this->map());
    }

    /** @return class-string|null La classe d'un type, ou null si le type est inconnu. */
    public function classFor(string $type): ?string
    {
        return $this->map()[strtolower($type)] ?? null;
    }

    /** Le type (nom court minuscule) d'une classe, ou null si hors corbeille. */
    public function typeFor(string $fqcn): ?string
    {
        $type = array_search($fqcn, $this->map(), true);

        return $type === false ? null : $type;
    }

    /** Libellé lisible d'un type (« ticket » -> « Ticket »). */
    public function libelle(string $type): string
    {
        return ucfirst(strtolower($type));
    }
}

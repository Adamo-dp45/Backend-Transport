<?php

namespace App\Domain\Service;

use App\Entity\Output\Corbeille\CorbeilleItemDto;
use App\Entity\Output\Corbeille\CorbeilleListeDto;
use App\Repository\EntrepriseRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Cœur de la corbeille SUPER ADMIN, MULTI-ENTREPRISES. Le super admin échappe au périmètre entreprise
 * (EntrepriseScopeExtension `return` tôt pour lui) : on requête donc directement `deletedAt IS NOT NULL`
 * SANS filtre entreprise (sauf filtre explicite demandé). Aucune dépendance à l'entreprise de l'acteur
 * (le super admin n'en a pas) — l'entreprise affichée est celle de CHAQUE élément.
 */
class CorbeilleService
{
    /** Plafond d'éléments listés PAR TYPE (les compteurs, eux, restent exacts). */
    private const LIMITE_PAR_TYPE = 200;

    /** Getters candidats pour un libellé humain, dans l'ordre de préférence. */
    private const GETTERS_LIBELLE = [
        'getCodeticket', 'getCodecourrier', 'getCodebagage', 'getCodevoyage',
        'getMatricule', 'getReference', 'getLibelle', 'getNom',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CorbeilleRegistry $registry,
        private readonly EntrepriseRepository $entrepriseRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * @param string[]|null $filtreTypes      Restreint les ÉLÉMENTS aux types donnés (null/[] = tous)
     * @param int|null      $filtreEntreprise Restreint les ÉLÉMENTS à une entreprise (null = toutes)
     */
    public function lister(?array $filtreTypes, ?int $filtreEntreprise): CorbeilleListeDto
    {
        $libelles = $this->libellesEntreprises();

        // ── Facettes (options de filtre + badges) — volontairement STABLES ──
        // parEntreprise : GLOBALE (tous types) → la liste des entreprises ne se vide jamais.
        // parType       : comptée pour l'entreprise sélectionnée (ou toutes) → chiffres justes du périmètre.
        // Aucune n'applique le filtre de TYPE, sinon les autres types disparaîtraient des options.
        $parType = [];
        $parEntrepriseCount = [];
        $total = 0;
        foreach ($this->registry->types() as $type) {
            $rows = $this->em->getRepository($this->registry->classFor($type))
                ->createQueryBuilder('e')
                ->select('e.identreprise AS ident, COUNT(e.id) AS nb')
                ->where('e.deletedAt IS NOT NULL')
                ->groupBy('e.identreprise')
                ->getQuery()
                ->getScalarResult();

            $countType = 0;
            foreach ($rows as $row) {
                $nb = (int) $row['nb'];
                $ident = $row['ident'] !== null ? (int) $row['ident'] : null;
                if ($ident !== null) {
                    $parEntrepriseCount[$ident] = ($parEntrepriseCount[$ident] ?? 0) + $nb;
                }
                if ($filtreEntreprise === null || $ident === $filtreEntreprise) {
                    $countType += $nb;
                }
            }
            if ($countType > 0) {
                $parType[] = ['type' => $type, 'typeLibelle' => $this->registry->libelle($type), 'count' => $countType];
                $total += $countType;
            }
        }

        // ── Éléments : filtrés (type ET entreprise), limités par type, plus récents d'abord ──
        $auteurs = [];
        $items = [];
        foreach ($this->typesValides($filtreTypes) as $type) {
            $qb = $this->em->getRepository($this->registry->classFor($type))
                ->createQueryBuilder('e')
                ->where('e.deletedAt IS NOT NULL')
                ->orderBy('e.deletedAt', 'DESC')
                ->setMaxResults(self::LIMITE_PAR_TYPE);
            $this->filtreEntreprise($qb, $filtreEntreprise);

            foreach ($qb->getQuery()->getResult() as $entity) {
                $ident = $entity->getIdentreprise();
                $deletedBy = $entity->getDeletedBy();
                if ($deletedBy !== null && !array_key_exists($deletedBy, $auteurs)) {
                    $u = $this->userRepository->find($deletedBy);
                    $auteurs[$deletedBy] = $u ? trim(($u->getPrenom() ?? '') . ' ' . ($u->getNom() ?? '')) : null;
                }
                $items[] = new CorbeilleItemDto(
                    type: $type,
                    typeLibelle: $this->registry->libelle($type),
                    id: (int) $entity->getId(),
                    libelle: $this->label($entity, $type),
                    identreprise: $ident,
                    entreprise: $ident !== null ? ($libelles[$ident] ?? null) : null,
                    deletedAt: $entity->getDeletedAt()?->format('Y-m-d H:i'),
                    deletedBy: $deletedBy !== null ? $auteurs[$deletedBy] : null,
                );
            }
        }

        // Tri global : suppressions les plus récentes en tête, tous types confondus.
        usort($items, static fn (CorbeilleItemDto $a, CorbeilleItemDto $b) => ($b->deletedAt ?? '') <=> ($a->deletedAt ?? ''));

        $parEntreprise = [];
        foreach ($parEntrepriseCount as $id => $count) {
            $parEntreprise[] = ['id' => $id, 'libelle' => $libelles[$id] ?? ('#' . $id), 'count' => $count];
        }
        usort($parEntreprise, static fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return new CorbeilleListeDto($total, $parType, $parEntreprise, $items);
    }

    public function restaurer(string $type, int $id): void
    {
        $this->reactiver($this->resoudre($type, $id));
        $this->em->flush();
    }

    public function purger(string $type, int $id): void
    {
        $entity = $this->resoudre($type, $id);
        try {
            $this->em->remove($entity);
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new UnprocessableEntityHttpException(
                'Suppression définitive impossible : cet enregistrement est encore référencé par d\'autres données. Détachez-les d\'abord.'
            );
        }
    }

    /** @param string[]|null $types */
    public function restaurerLot(?array $types, ?int $entreprise): int
    {
        $count = 0;
        foreach ($this->supprimes($types, $entreprise) as $entity) {
            $this->reactiver($entity);
            $count++;
        }
        $this->em->flush();

        return $count;
    }

    /**
     * Vidage TRANSACTIONNEL (tout ou rien) : si un seul élément est encore référencé (FK), rien n'est
     * purgé et on renvoie une erreur claire — plus sûr qu'une purge partielle silencieuse.
     *
     * @param string[]|null $types
     */
    public function viderLot(?array $types, ?int $entreprise): int
    {
        $count = 0;
        try {
            foreach ($this->supprimes($types, $entreprise) as $entity) {
                $this->em->remove($entity);
                $count++;
            }
            $this->em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new UnprocessableEntityHttpException(
                'Suppression définitive impossible : certains éléments sont encore référencés par d\'autres données. Aucun n\'a été supprimé.'
            );
        }

        return $count;
    }

    /**
     * Tous les enregistrements soft-deletés des types demandés (filtre entreprise optionnel).
     *
     * @param  string[]|null $types
     * @return iterable<object>
     */
    private function supprimes(?array $types, ?int $entreprise): iterable
    {
        foreach ($this->typesValides($types) as $type) {
            $qb = $this->em->getRepository($this->registry->classFor($type))
                ->createQueryBuilder('e')
                ->where('e.deletedAt IS NOT NULL');
            $this->filtreEntreprise($qb, $entreprise);
            yield from $qb->getQuery()->getResult();
        }
    }

    /** Résout un élément EN CORBEILLE (soft-deleté), toutes entreprises. */
    private function resoudre(string $type, int $id): object
    {
        $class = $this->registry->classFor($type);
        if ($class === null) {
            throw new BadRequestHttpException('Type invalide : ' . $type);
        }
        $entity = $this->em->getRepository($class)->find($id);
        if ($entity === null || $entity->getDeletedAt() === null) {
            throw new NotFoundHttpException('Élément introuvable en corbeille');
        }

        return $entity;
    }

    private function reactiver(object $entity): void
    {
        $entity->setDeletedAt(null)->setDeletedBy(null);
        if (method_exists($entity, 'setIsEtatdelete')) {
            $entity->setIsEtatdelete(false);
        }
    }

    private function filtreEntreprise(QueryBuilder $qb, ?int $entreprise): void
    {
        if ($entreprise !== null) {
            $alias = $qb->getRootAliases()[0];
            $qb->andWhere("$alias.identreprise = :entreprise")->setParameter('entreprise', $entreprise);
        }
    }

    private function label(object $entity, string $type): string
    {
        foreach (self::GETTERS_LIBELLE as $getter) {
            if (method_exists($entity, $getter)) {
                $value = $entity->$getter();
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        return $this->registry->libelle($type) . ' #' . $entity->getId();
    }

    /**
     * Normalise la liste de types : null/vide => tous les types corbeillables ; sinon on ne garde que
     * les types réellement connus (un type inconnu est ignoré, pas une erreur).
     *
     * @param  string[]|null $types
     * @return string[]
     */
    private function typesValides(?array $types): array
    {
        $connus = $this->registry->types();
        if (empty($types)) {
            return $connus;
        }
        $demandes = array_map('strtolower', $types);

        return array_values(array_intersect($connus, $demandes));
    }

    /** @return array<int, string> id entreprise => libellé */
    private function libellesEntreprises(): array
    {
        $map = [];
        foreach ($this->entrepriseRepository->findAll() as $entreprise) {
            $map[$entreprise->getId()] = $entreprise->getLibelle();
        }

        return $map;
    }
}

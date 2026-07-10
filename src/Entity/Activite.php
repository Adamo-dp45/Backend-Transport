<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\ActiviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Journal d'activité : « qui a fait quoi » dans l'application. Écrit UNIQUEMENT en interne par
 * 'ActiviteLogger' (jamais via l'API → pas de Post/Patch/Delete) ; lecture seule. Scopé par entreprise.
 *
 * - 'cibletype' + 'cibleid' = référence légère vers l'objet concerné (ex. 'Voyage' / 42) → on peut
 *   filtrer la timeline d'un objet sans FK vers chaque entité, et ça s'étend à toute l'app.
 * - 'auteur' = relation vers le User (le nom affiché suit les changements de profil), 'createdBy' = son id.
 */
#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Index(name: 'idx_activite_cible', columns: ['cibletype', 'cibleid'])]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Activite', 'read:Base']],
    paginationItemsPerPage: 30,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Activite') or is_granted('ROLE_USER')",
            openapi: new Operation(
                summary: 'Journal d\'activité (qui a fait quoi)',
                description: 'Liste les événements ; filtrer par cibletype/cibleid pour la timeline d\'un objet',
                security: [['bearerAuth' => []]]
            )
        ),
    ],
    openapi: new Operation(security: [['bearerAuth' => []]])
)]
#[ApiFilter(SearchFilter::class, properties: [
    'cibletype' => 'exact',
    'cibleid' => 'exact',
    'type' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'id'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Activite extends EntityBase implements EntrepriseOwnedInterface
{
    use IdEntrepriseTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Activite'])]
    private ?int $id = null;

    /** Catégorie de l'événement (ex. VOYAGE_CAR, VOYAGE_COMMERCIAL, VOYAGE_RECEPTION…) → icône/filtre côté front */
    #[ORM\Column(length: 60)]
    #[Groups(['read:Activite'])]
    private ?string $type = null;

    /** Texte lisible (ex. « Car changé : AB-123-CD → EF-456-GH ») */
    #[ORM\Column(length: 255)]
    #[Groups(['read:Activite'])]
    private ?string $libelle = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['read:Activite'])]
    private ?string $cibletype = null; // ex. 'Voyage'

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Activite'])]
    private ?int $cibleid = null;

    /** L'acteur (qui a fait l'action) — relation pour refléter son nom courant */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    #[Groups(['read:Activite'])]
    private ?User $auteur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getCibletype(): ?string
    {
        return $this->cibletype;
    }

    public function setCibletype(?string $cibletype): static
    {
        $this->cibletype = $cibletype;

        return $this;
    }

    public function getCibleid(): ?int
    {
        return $this->cibleid;
    }

    public function setCibleid(?int $cibleid): static
    {
        $this->cibleid = $cibleid;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }
}

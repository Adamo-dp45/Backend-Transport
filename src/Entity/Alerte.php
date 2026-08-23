<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Enum\AlerteStatut;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Repository\AlerteRepository;
use App\State\AlerteLireProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * ALERTE persistée du centre de notifications interne (FT). Générée automatiquement par le
 * balayeur (AlerteGenerationService / commande app:alertes:generer) qui réconcilie l'état courant
 * de l'application : jamais créée via l'API (aucune opération Post). Le ciblage (audience) est
 * porté par AlerteAudienceExtension (via AlerteAudienceResolver), en plus du périmètre entreprise
 * assuré par EntrepriseScopeExtension. Seules deux lectures et une action « marquer lu » sont exposées.
 */
#[ORM\Entity(repositoryClass: AlerteRepository::class)]
#[ORM\Index(name: 'idx_alerte_ent_cle', columns: ['identreprise', 'cle'])]
#[ORM\Index(name: 'idx_alerte_ent_statut', columns: ['identreprise', 'statut'])]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Alerte', 'read:Base']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['createdAt' => 'DESC'],
    operations: [
        new GetCollection(
            openapi: new Operation(
                summary: 'Liste des alertes visibles par l\'utilisateur courant',
                description: 'Alertes ciblées selon la gare/le rôle de l\'utilisateur (audience). Filtres : type, severite, statut, portee, famille.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            requirements: ['id' => '\d+'],
            openapi: new Operation(security: [['bearerAuth' => []]])
        ),
        new Patch(
            uriTemplate: '/alertes/{id}/lire',
            requirements: ['id' => '\d+'],
            input: false,
            processor: AlerteLireProcessor::class,
            openapi: new Operation(
                summary: 'Marquer une alerte comme lue',
                description: 'Acquitte l\'alerte (statut LUE) : elle sort du compteur de la cloche mais reste au listing tant que la situation persiste.',
                security: [['bearerAuth' => []]]
            )
        ),
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'type'     => 'exact',
    'severite' => 'exact',
    'statut'   => 'exact',
    'portee'   => 'exact',
    'famille'  => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'createdAt', 'severite'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
class Alerte extends EntityBase implements EntrepriseOwnedInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Alerte'])]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    #[Groups(['read:Alerte'])]
    private ?string $type = null;

    #[ORM\Column(length: 20)]
    #[Groups(['read:Alerte'])]
    private ?string $severite = null;

    #[ORM\Column(length: 20)]
    #[Groups(['read:Alerte'])]
    private ?string $portee = null;

    #[ORM\Column(length: 30)]
    #[Groups(['read:Alerte'])]
    private ?string $famille = null;

    // Gare concernée quand portee = GARE ; NULL pour ENTREPRISE / DIRECTION.
    #[ORM\Column(nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?int $idgare = null;

    // Clé de déduplication (TYPE:sourceid[:sous-clé]) : garantit l'idempotence du balayeur.
    #[ORM\Column(length: 255)]
    #[Groups(['read:Alerte'])]
    private ?string $cle = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Alerte'])]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['read:Alerte'])]
    private ?string $message = null;

    // Référence polymorphe vers l'objet source (VOYAGE, PIECE, RESERVATION…) → lien cliquable FT.
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?string $sourcetype = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?int $sourceid = null;

    // Charge utile additionnelle (ex. nombre d'évincés, montant, taux…), affichée telle quelle côté FT.
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?array $donnees = null;

    #[ORM\Column(length: 20)]
    #[Groups(['read:Alerte'])]
    private string $statut = AlerteStatut::ACTIVE->value;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?int $luePar = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?\DateTimeImmutable $lueLe = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:Alerte'])]
    private ?\DateTimeImmutable $resolueLe = null;

    #[ORM\Column(nullable: true)]
    private ?int $identreprise = null;

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

    public function getSeverite(): ?string
    {
        return $this->severite;
    }

    public function setSeverite(string $severite): static
    {
        $this->severite = $severite;

        return $this;
    }

    public function getPortee(): ?string
    {
        return $this->portee;
    }

    public function setPortee(string $portee): static
    {
        $this->portee = $portee;

        return $this;
    }

    public function getFamille(): ?string
    {
        return $this->famille;
    }

    public function setFamille(string $famille): static
    {
        $this->famille = $famille;

        return $this;
    }

    public function getIdgare(): ?int
    {
        return $this->idgare;
    }

    public function setIdgare(?int $idgare): static
    {
        $this->idgare = $idgare;

        return $this;
    }

    public function getCle(): ?string
    {
        return $this->cle;
    }

    public function setCle(string $cle): static
    {
        $this->cle = $cle;

        return $this;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getSourcetype(): ?string
    {
        return $this->sourcetype;
    }

    public function setSourcetype(?string $sourcetype): static
    {
        $this->sourcetype = $sourcetype;

        return $this;
    }

    public function getSourceid(): ?int
    {
        return $this->sourceid;
    }

    public function setSourceid(?int $sourceid): static
    {
        $this->sourceid = $sourceid;

        return $this;
    }

    public function getDonnees(): ?array
    {
        return $this->donnees;
    }

    public function setDonnees(?array $donnees): static
    {
        $this->donnees = $donnees;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getLuePar(): ?int
    {
        return $this->luePar;
    }

    public function setLuePar(?int $luePar): static
    {
        $this->luePar = $luePar;

        return $this;
    }

    public function getLueLe(): ?\DateTimeImmutable
    {
        return $this->lueLe;
    }

    public function setLueLe(?\DateTimeImmutable $lueLe): static
    {
        $this->lueLe = $lueLe;

        return $this;
    }

    public function getResolueLe(): ?\DateTimeImmutable
    {
        return $this->resolueLe;
    }

    public function setResolueLe(?\DateTimeImmutable $resolueLe): static
    {
        $this->resolueLe = $resolueLe;

        return $this;
    }

    public function getIdentreprise(): ?int
    {
        return $this->identreprise;
    }

    public function setIdentreprise(?int $identreprise): static
    {
        $this->identreprise = $identreprise;

        return $this;
    }
}

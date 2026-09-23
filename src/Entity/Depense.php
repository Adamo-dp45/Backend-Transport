<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\GareOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\DepenseRepository;
use App\State\DepenseProcessor;
use App\State\SoftDeleteProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une CHARGE d'exploitation saisie à la main : carburant, péage, pneus, salaires, loyer, imprévu.
 *
 * L'application savait tout de ce qui RENTRE (RecetteGareService, trois canaux) et presque rien de
 * ce qui SORT — seuls l'achat de pièces et le dépannage portaient un coût. C'est ce qui empêchait
 * de répondre à « la compagnie gagne-t-elle réellement de l'argent, et quelle gare ? ».
 *
 * !! CETTE ENTITÉ NE PORTE QUE LES CHARGES SAISIES À LA MAIN. Un dépannage ou un approvisionnement
 * ne produit JAMAIS de dépense : leurs coûts restent sur leurs entités et sont soustraits à part
 * (cf. 'FinancierStatsProvider'). Dériver l'un de l'autre les compterait deux fois, et le double
 * comptage serait alors TECHNIQUE, donc invisible. Verrouillé par 'tests/Api/DepenseTest.php'.
 *
 * DEUX PORTÉES, un seul discriminant : 'gare' renseignée = dépense de cette gare ; 'gare' NULLE =
 * dépense du SIÈGE (loyer, salaires de la direction). Pas de colonne 'portee' à côté, qui pourrait
 * diverger de la relation — même choix que 'Role::$gare'.
 */
#[ORM\Entity(repositoryClass: DepenseRepository::class)]
/*
    - Index déclarés ICI et pas seulement dans la migration : la base de TEST est construite par
      'doctrine:schema:update' (cf. 'make test-db'), qui ne connaît que les mappings — un index posé
      par la seule migration n'y existerait pas, et les tests mesureraient un autre schéma.
*/
#[ORM\Index(name: 'idx_depense_ent_date', columns: ['identreprise', 'datedepense'])]
#[ORM\Index(name: 'idx_depense_ent_gare_date', columns: ['identreprise', 'gare_id', 'datedepense'])]
#[ORM\Index(name: 'idx_depense_voyage', columns: ['voyage_id'])]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['read:Depense', 'read:Base']],
    denormalizationContext: ['groups' => ['write:Depense']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    order: ['datedepense' => 'DESC'],
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Depense')", /*
                - Le périmètre de GARE est porté par 'GareScopeExtension' (cf. gareScopeField) : un
                  agent rattaché ne voit que sa gare, donc jamais les dépenses du siège ('d.gare = :id'
                  est faux pour NULL). Admin et utilisateur central sans gare voient tout
            */
            openapi: new Operation(
                summary: 'Liste des dépenses',
                description: 'Permet de voir la liste des dépenses',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'],
            openapi: new Operation(
                summary: 'La dépense',
                description: 'Permet de voir une dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Depense')",
            processor: DepenseProcessor::class,
            openapi: new Operation(
                summary: 'Enregistrement d’une dépense',
                description: 'Permet d’enregistrer une dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'],
            processor: DepenseProcessor::class,
            openapi: new Operation(
                summary: 'Modification d’une dépense',
                description: 'Permet de modifier une dépense',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')", /*
                - Une sortie d'argent est un document : sa mise en corbeille est réservée à
                  l'administrateur d'entreprise, comme pour 'Approvisionnement'. La permission
                  'SUPPRIMER' seule ne suffit donc pas
            */
            name: 'Remove_Depense',
            uriTemplate: '/depenses/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille de la dépense',
                description: 'Permet de mettre une dépense en corbeille',
                security: [['bearerAuth' => []]]
            )
        )
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]]
    )
)]
#[ApiFilter(SearchFilter::class, properties: [
    'libelle' => 'partial',
    'beneficiaire' => 'partial',
    'typedepense.id' => 'exact',
    'gare.id' => 'exact',
    'fournisseur.id' => 'exact',
    'voyage.id' => 'exact',
    'modereglement' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'datedepense', 'montant', 'createdAt'])]
#[ApiFilter(DateFilter::class, properties: ['datedepense'])]
#[ApiFilter(ExistsFilter::class, properties: ['gare'])] /*
    - '?exists[gare]=false' isole les dépenses du SIÈGE : 'mysql' ne comprend pas 'gare=null' comme
      filtre de recherche (même raison que 'datearriveereelle' sur 'Voyage')
*/
class Depense extends EntityBase implements EntrepriseOwnedInterface, GareOwnedInterface, HasSoftDeleteGuard
{
    use IdEntrepriseTrait;

    /** Périmètre C : une dépense appartient à UNE gare — ou au siège, et alors à aucune. */
    public static function gareScopeField(): string
    {
        return 'gare';
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Depense'])]
    private ?int $id = null;

    /**
     * DATETIME et non DATE : 'PeriodeTrait::parsePeriode()' borne la fin de période à 23:59:59, une
     * colonne DATE ferait tomber la dernière journée hors des statistiques.
     */
    #[ORM\Column]
    #[Groups(['read:Depense', 'write:Depense'])]
    #[Assert\NotNull(message: 'La date de la dépense est obligatoire')]
    private ?\DateTimeImmutable $datedepense = null;

    /**
     * BIGINT : un total en FCFA peut dépasser la limite INT (~2,1 milliards) — hors d'atteinte pour
     * un péage, pas pour un lot de salaires ou l'achat d'un véhicule. Même choix que
     * 'Depannage::$couttotal' et 'Detailapprovisionnement::$couttotal'.
     */
    #[ORM\Column(type: 'bigint')]
    #[Groups(['read:Depense', 'write:Depense'])]
    #[Assert\NotNull(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à zéro')] /*
        - Un montant négatif REMONTERAIT le bénéfice : c'est une porte d'abus, pas une commodité de
          saisie. Une correction se fait en modifiant la dépense, jamais en en créant une négative
    */
    private ?int $montant = null;

    #[ORM\ManyToOne(inversedBy: 'depenses')]
    #[ORM\JoinColumn(nullable: false)] // onDelete: 'RESTRICT'
    #[Groups(['read:Depense', 'write:Depense'])]
    #[Assert\NotNull(message: 'Le type de dépense est obligatoire')]
    private ?Typedepense $typedepense = null;

    /**
     * NULL = dépense du SIÈGE. C'est le seul discriminant de portée (cf. l'en-tête de la classe).
     * En écriture, 'DepenseProcessor' l'auto-affecte à la gare de l'acteur et refuse qu'un agent
     * rattaché impute ailleurs — ou au siège.
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?Gare $gare = null;

    /**
     * CROCHET des « frais de route » (forfait remis à l'équipage pour un départ) : le champ existe
     * pour que le résultat d'un voyage se DÉRIVE un jour (ventes − dépenses du voyage), sans reprise
     * de schéma ni double comptage. Aucun écran ne l'expose aujourd'hui.
     */
    #[ORM\ManyToOne]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?Voyage $voyage = null;

    #[ORM\Column(length: 20, options: ['default' => 'ESPECES'])]
    #[Groups(['read:Depense', 'write:Depense'])]
    #[Assert\Choice(
        choices: ['ESPECES', 'MOBILE_MONEY', 'VIREMENT', 'CHEQUE'],
        message: 'Mode de règlement inconnu'
    )] // cf. App\Domain\Enum\Modereglement
    private string $modereglement = 'ESPECES';

    /**
     * À QUI l'argent a été versé, en clair : « Mairie de Bouaké », « station Total », « équipe de
     * nuit ». Texte libre PLUS 'fournisseur' optionnel : 'Fournisseur' exige contact, adresse et
     * pays — imposer une fiche pour payer un péage produirait des fiches bidon.
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?string $beneficiaire = null;

    #[ORM\ManyToOne(inversedBy: 'depenses')]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?Fournisseur $fournisseur = null;

    /** Reçu ou facture. Images ET PDF (cf. la contrainte assouplie sur 'MediaObject'). */
    #[ORM\ManyToOne]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?MediaObject $justificatif = null;

    /**
     * Le « pour quoi » en clair. C'est aussi ce que la CORBEILLE affiche pour identifier la ligne
     * ('CorbeilleService' cherche un 'getLibelle') — d'où ce nom plutôt que « motif ».
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['read:Depense', 'write:Depense'])]
    private ?string $libelle = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Portée DÉRIVÉE de la relation, jamais stockée : deux sources de vérité finiraient par
     * diverger (une ligne « portee = GARE » avec 'gare' nulle ne voudrait rien dire).
     */
    #[Groups(['read:Depense'])]
    #[SerializedName('portee')]
    public function getPortee(): string
    {
        return $this->gare === null ? 'ENTREPRISE' : 'GARE';
    }

    public function getDatedepense(): ?\DateTimeImmutable
    {
        return $this->datedepense;
    }

    public function setDatedepense(\DateTimeImmutable $datedepense): static
    {
        $this->datedepense = $datedepense;

        return $this;
    }

    public function getMontant(): ?int
    {
        return $this->montant;
    }

    public function setMontant(int $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getTypedepense(): ?Typedepense
    {
        return $this->typedepense;
    }

    public function setTypedepense(?Typedepense $typedepense): static
    {
        $this->typedepense = $typedepense;

        return $this;
    }

    public function getGare(): ?Gare
    {
        return $this->gare;
    }

    public function setGare(?Gare $gare): static
    {
        $this->gare = $gare;

        return $this;
    }

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): static
    {
        $this->voyage = $voyage;

        return $this;
    }

    public function getModereglement(): string
    {
        return $this->modereglement;
    }

    public function setModereglement(string $modereglement): static
    {
        $this->modereglement = $modereglement;

        return $this;
    }

    public function getBeneficiaire(): ?string
    {
        return $this->beneficiaire;
    }

    public function setBeneficiaire(?string $beneficiaire): static
    {
        $this->beneficiaire = $beneficiaire;

        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): static
    {
        $this->fournisseur = $fournisseur;

        return $this;
    }

    public function getJustificatif(): ?MediaObject
    {
        return $this->justificatif;
    }

    public function setJustificatif(?MediaObject $justificatif): static
    {
        $this->justificatif = $justificatif;

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    /**
     * Rien ne bloque la mise en corbeille d'une dépense : elle ne porte aucune écriture dérivée
     * (pas de mouvement de stock, pas de place rendue). Le contrat est tenu pour que l'entité
     * traverse 'SoftDeleteProcessor' comme les autres.
     */
    public function getSoftDeleteBlockers(): array
    {
        return [];
    }
}

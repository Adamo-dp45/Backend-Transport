<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Interface\HasSoftDeleteGuard;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\TypepieceRepository;
use App\State\EntrepriseInjectionProcessor;
use App\State\SoftDeleteProcessor;
use App\State\UpdatedbyProcessor;
use App\Validator\UniquePerEntreprise;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: TypepieceRepository::class)]
/*
    #[UniqueEntity(
        fields: ['libelle', 'identreprise', 'deletedAt'], -- Va nous bloquer si on veut remove un enrgistrement déjà remove ou crée un enregistrement déjà existant qui a 'deleted_at' à 'NUll'
        message: 'L\'enregistrement existe déjà pour votre entreprise'
    )] -- Va s'activer lors de la validation d'un formulaire ou de la logique métier

    #[ORM\UniqueConstraint(
        name: 'typepiece_libelle_entreprise_unique',
        columns: ['libelle', 'identreprise', 'deleted_at'], -- 'deleted_at' ne marchera pas car dans 'mysql' la valeur 'NULL' != 'NULL'
        - options: ['where' => '(deleted_at IS NULL)'] -- 'index partiel' mais fonctionne avec 'postgreSQL'
    )]
*/
#[UniquePerEntreprise( /*
    - Va prendre en compte le 'deletedAt' et 'identreprise'
*/
    fields: ['libelle'],
    message: 'Le type de pièce existe déjà dans votre entreprise'
)]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: [
        'groups' => ['read:Typepiece', 'read:Base'],
        'openapi_definition_name' => 'Collection',
        'skip_null_values' => false /*
            - Vu que 'ApiPlatform' ne renvoi pas un champ s'il est 'null' on peut désactiver son comportement ainsi
        */
    ],
    denormalizationContext: ['groups' => ['write:Typepiece']],
    paginationEnabled: false, // Vu qu'on vas utilisé 'DataTables'
    order: ['createdAt' => 'DESC'], // Pour piloter l'ordre
    operations: [
        new GetCollection(
            security: "is_granted('VOIR', 'Typepiece') or is_granted('VOIR', 'Piece')", /*
                - Pour le filtre du 'identreprise' on l'a fais dans 'EntrepriseScopeExtension'
                - !! on pouvait le faire dans l'option 'provider' mais lui fais remplace totalement la récupération, aussi avec lui 'ApiPlatform' n'utilise plus automatiquement 'Doctrine' et donc les extensions ne seront plus appelées
                - - 
                    - L'option 'controller' permet de remplacer complètement le comportement automatique de 'ApiPlatform' et nous donne le contrôle total de la requête
                    - !! 'provider' !! de personnaliser la récupération 'get', 'getCollection' et aussi charger les données depuis doctrine, une api externe ou un microservice
                    - !! 'processor' !! personnaliser la persistance 'écriture'
            */
            /*
                extraProperties: [
                    'filtre_class' => Piece::class,
                    'name' => 'Pie..'
                ] -- Permet d'ajouter plus d'informations dans la requête, utile pour les 'provider' personnalisé
            */
            openapi: new Operation(
                summary: 'Liste des types de pièces',
                description: 'Permet de voir la liste des types de pièces',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object)",
            requirements: ['id' => '\d+'], /*
                - Pour le filtre du 'identreprise' on l'a fais dans 'EntrepriseScopeExtension'
            */
            /*
                - securityMessage: 'Seul l\'administrateur peut accéder à la corbeille', -- Va apparaître dans 'detail' de la reponse
                - exceptionToStatus: [AccessDeniedException::class => 403], -- Pour personnaliser les erreurs 'ApiPlatform'
            */
            openapi: new Operation(
                summary: 'Le type de pièce',
                description: 'Permet de voir un type de pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'Typepiece')",
            processor: EntrepriseInjectionProcessor::class, /*
                - Au lieu d'un 'processor' on peut utiliser un 'Denormalizer' ou un 'controller' par contre on '->flush()' vu qu'on sort du cardre de 'ApiPlatform'
                - validationContext: ['groups' => ['Default', 'write:User']] -- 'Default' pour le gloable
            */
            openapi: new Operation(
                summary: 'Création du type de pièce',
                description: 'Permet de créer un type de pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)",
            requirements: ['id' => '\d+'], /* -- Le remplaçement partiel 'patch'
                - Pour le filtre du 'identreprise' on l'a fais dans 'EntrepriseScopeExtension'
            */
            processor: UpdatedbyProcessor::class,
            openapi: new Operation(
                summary: 'Modification du type de pièce',
                description: 'Permet de modifier un type de pièce',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch( /*
            - Content-Type: 'application/merge-patch+json'
            - Si 'input' non définie alors on à besoin d'un body '{}' même vide
        */
            security: "is_granted('SUPPRIMER', object)",
            name: 'Remove_Typepiece',
            uriTemplate: '/typepieces/{id}/remove',
            requirements: ['id' => '\d+'],
            input: false, /*
                - 'deserialize: false' permet de ne pas désérialiser le body json dans l'entité, même si l'objet ne sera pas modifié le body existe quand même mais il est ignoré
                - 'input: false' !! et indique à 'ApiPlatform' qu'il n'y a pas de body ce qui permet à 'Swagger' de ne pas montré le schéma d'entrée
                - On peut utiliser un 'Dto' vide puis 'input: SoftDeleteInput::class'
            */
            processor: SoftDeleteProcessor::class,
            openapi: new Operation(
                summary: 'Mise en corbeille du type de pièce',
                description: 'Permet de mettre un type de pièce en corbeille',
                security: [['bearerAuth' => []]]
            )
        ) /*
            new Patch(
                security: "is_granted('ROLE_SUPER_ADMIN')",
                name: 'Restore_Typepiece',
                uriTemplate: '/typepieces/{id}/restore',
                processor: RestoreProcessor::class,
                input: false,
                description: 'Permet de restauré un enregistrement',
                requirements: ['id' => '\d+'],
                openapi: new Operation(
                    summary: 'Restauration du type de pièce',
                    description: 'Permet de restauré un type de pièce en corbeille',
                    security: [['bearerAuth' => []]]
                )
            )
        */
    ],
    openapi: new Operation(
        security: [['bearerAuth' => []]] /*
            - Pour indiquer au niveau de la documentation 'openapi' qu'une route est protégé
        */
    )
)]
class Typepiece extends EntityBase implements EntrepriseOwnedInterface, HasSoftDeleteGuard
{
    use IdEntrepriseTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:Typepiece', 'read:Piece'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:Typepiece', 'write:Typepiece', 'read:Piece'])]
    #[ApiProperty(
        types: 'string',
        description: 'Le libellé du type de pièce',
        example: 'Filtre à air'
    )] /*
        - Permet de documenté la propriété de manière gloable
    */
    #[Assert\Length(min: 2)]
    private ?string $libelle = null;

    /**
     * @var Collection<int, Piece>
     */
    #[ORM\OneToMany(targetEntity: Piece::class, mappedBy: 'typepiece')]
    private Collection $pieces;

    public function __construct()
    {
        $this->pieces = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, Piece>
     */
    public function getPieces(): Collection
    {
        return $this->pieces;
    }

    public function addPiece(Piece $piece): static
    {
        if (!$this->pieces->contains($piece)) {
            $this->pieces->add($piece);
            $piece->setTypepiece($this);
        }

        return $this;
    }

    public function removePiece(Piece $piece): static
    {
        if ($this->pieces->removeElement($piece)) {
            // set the owning side to null (unless already changed)
            if ($piece->getTypepiece() === $this) {
                $piece->setTypepiece(null);
            }
        }

        return $this;
    }

    public function getSoftDeleteBlockers(): array
    {
        $errors = [];

        $piecesNotDeleted = $this->pieces->filter(
            fn(Piece $v) => $v->getDeletedAt() === null
        );

        if(!$piecesNotDeleted->isEmpty()) {
            $errors[] = sprintf(
                'Le type de pièce est liée à %d pièce(s) actif(s).',
                $piecesNotDeleted->count()
            );
        }

        return $errors;
    }
}

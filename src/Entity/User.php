<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Controller\Api\PostAvatarController;
use App\Domain\Enum\ReferenceStatus;
use App\Entity\Dto\ChangePasswordInput;
use App\Entity\Dto\ForgotPasswordInput;
use App\Entity\Dto\RegisterInput;
use App\Entity\Dto\ResetPasswordInput;
use App\Entity\Dto\SupprimerMonCompteInput;
use App\Repository\UserRepository;
use App\State\ChangePasswordProcessor;
use App\State\SupprimerMonCompteProcessor;
use App\State\ForgotPasswordProcessor;
use App\State\MeProvider;
use App\State\PromouvoirAdminGareProcessor;
use App\State\PromouvoirUserProcessor;
use App\State\RegisterProcessor;
use App\State\ResetPasswordProcessor;
use App\State\SuspendreUserProcessor;
use App\State\UserProcessor;
use App\State\UserProfileProcessor;
use ArrayObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Entity\Interface\GareOwnedInterface;
use Vich\UploaderBundle\Mapping\Attribute\Uploadable;
use Vich\UploaderBundle\Mapping\Attribute\UploadableField;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ApiResource(
    normalizationContext: ['groups' => ['read:User'], 'skip_null_values' => false],
    denormalizationContext: ['groups' => ['write:User']],
    paginationItemsPerPage: 25,
    paginationClientItemsPerPage: true,
    operations: [
        new Post(
            name: 'Register',
            uriTemplate: '/register',
            input: RegisterInput::class,
            processor: RegisterProcessor::class, /*
                - Va contenir la logique de l'inscription et ne fonctionne pas sur 'getCollection' et 'get', va traiter l'objet avant persistance
            */
            denormalizationContext: ['groups' => ['write:Register']], /*
                - Pour éviter qu'il utilise 'write:User' sinon il ne vas pas remplir mon 'input' ou utiliser un groupe de denormalization sur le 'RegisterInput' ce qui va le permettre de documenté
            */
            status: Response::HTTP_CREATED,
            openapi: new Operation(
                summary: 'Permet à un utilisateur de créer une entreprise et devenir administrateur',
                description: 'Crée un nouvel utilisateur et son entreprise'
            )
        ),
        new GetCollection(
            security: "is_granted('VOIR', 'User') or is_granted('ROLE_SUPER_ADMIN')", /*
                - Pour le filtre du 'entreprise' on l'a fais dans 'UserEntrepriseExtension'
            */
            openapi: new Operation(
                summary: 'La liste des utilisateurs',
                description: 'Permet de voir la liste des utilisateurs',
                security: [['bearerAuth' => []]]
            )
        ),
        new Get(
            security: "is_granted('VOIR', object) or is_granted('ROLE_SUPER_ADMIN')",
            requirements: ['id' => '\d+'], /*
                - Pour le filtre du 'entreprise' on l'a fais dans 'UserEntrepriseExtension'
            */
            openapi: new Operation(
                summary: 'L\'utilisateur',
                description: 'Permet de voir un utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('CREER', 'User')",
            processor: UserProcessor::class,
            openapi: new Operation(
                summary: 'Créer un utilisateur',
                description: 'Permet de créer un utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('MODIFIER', object)", /*
                - On bloque l'accès au super admin car c'est à l'admin de modifier son utilisateur par cause de 'userRoles'
            */
            requirements: ['id' => '\d+'], /*
                - Pour le filtre du 'entreprise' on l'a fais dans 'UserEntrepriseExtension'
            */
            processor: UserProcessor::class,
            openapi: new Operation(
                summary: 'Modifier un utilisateur',
                description: 'Permet de modifier un utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN') or is_granted('ROLE_ADMIN_GARE')",
            uriTemplate: '/users/{id}/suspendre',
            requirements: ['id' => '\d+'],
            input: false,
            processor: SuspendreUserProcessor::class,
            openapi: new Operation(
                summary: 'Suspendre ou réactiver un utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            uriTemplate: '/users/{id}/promouvoir', /*
                - Le principe, 'isFounder=true' est le fondateur de l'entreprise crée à l'inscription et peut promouvoir ou rétrograder tout le monde mais ne peut pas être modifié par un non fondateur, le promu peut tout voir et peut être rétrogradé ou suspendu par le fondateur
            */
            requirements: ['id' => '\d+'],
            input: false,
            processor: PromouvoirUserProcessor::class,
            openapi: new Operation(
                summary: 'Promouvoir ou rétrograder un utilisateur en administrateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            uriTemplate: '/users/{id}/promouvoir/gare',
            requirements: ['id' => '\d+'],
            input: false,
            processor: PromouvoirAdminGareProcessor::class,
            openapi: new Operation(
                summary: 'Nommer ou révoquer un administrateur de gare',
                security: [['bearerAuth' => []]]
            )
        ),
        /* Profil
         */
        new Get(
            security: "is_granted('ROLE_USER')",
            name: 'Me',
            uriTemplate: '/me',
            provider: MeProvider::class,
            paginationEnabled: false,
            normalizationContext: ['groups' => ['read:User']],
            openapi: new Operation(
                security: [['bearerAuth' => []]]
            )
        ),
        new Patch(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/me',
            provider: MeProvider::class, /*
                - Sans provider ni {id} dans l'URI, API Platform ne sait pas quel objet patcher (404).
                  MeProvider fournit l'utilisateur courant comme objet à mettre à jour.
            */
            denormalizationContext: ['groups' => ['write:User:profil']],
            processor: UserProfileProcessor::class,
            openapi: new Operation(
                summary: 'Modification du profil utilisateur',
                description: 'Permet de mettre un profil utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/me/password',
            input: ChangePasswordInput::class,
            processor: ChangePasswordProcessor::class,
            denormalizationContext: ['groups' => ['write:User:password']],
            openapi: new Operation(
                summary: 'Modification de mot de passe utilisateur',
                description: 'Permet de mettre un mot de passe utilisateur',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/me/supprimer',
            input: SupprimerMonCompteInput::class,
            processor: SupprimerMonCompteProcessor::class,
            denormalizationContext: ['groups' => ['write:User:supprimer']],
            openapi: new Operation(
                summary: 'Supprimer mon compte (= suspension après vérification du mot de passe)',
                description: 'L\'utilisateur supprime son compte : vérification du mot de passe puis suspension.',
                security: [['bearerAuth' => []]]
            )
        ),
        new Post(
            security: "is_granted('ROLE_USER')",
            uriTemplate: '/me/avatar',
            deserialize: false,
            controller: PostAvatarController::class,
            inputFormats: ['multipart' => ['multipart/form-data']], /*
                - Pour injecter le format dans la requête
            */
            openapi: new Operation(
                summary: 'Modification de l\'avatar',
                description: 'Permet de mettre à jour l\'avatar',
                security: [['bearerAuth' => []]],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
                                    ],
                                    'description' => 'Fichier image (JPEG, PNG, GIF, WEBP max 2Mo)'
                                ],
                                'required' => ['imageFile']
                            ]
                        ]
                    ])
                )
            )
        ),
        new Post(
            uriTemplate: '/forgot',
            denormalizationContext: ['groups' => ['write:ForgotPasswordInput']],
            input: ForgotPasswordInput::class,
            processor: ForgotPasswordProcessor::class,
            openapi: new Operation(
                summary: 'Demande de réinitialisation de mot de passe',
                description: 'Permet de demander la réinitialisation du mot de passe'
            )
        ),
        new Post(
            uriTemplate: '/reset',
            denormalizationContext: ['groups' => ['write:ResetPasswordInput']],
            input: ResetPasswordInput::class,
            processor: ResetPasswordProcessor::class,
            openapi: new Operation(
                summary: 'Modification du mot de passe',
                description: 'Permet de modifier le mot de passe'
            )
        )
        // Les autres routes sont dans 'OpenApiFactory' --
    ]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'nom' => 'partial',
    'email' => 'partial',
    'statut' => 'exact',
    'gare.id' => 'exact'
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'nom',
    'prenom'
])]
#[Uploadable()]
class User implements UserInterface, PasswordAuthenticatedUserInterface, GareOwnedInterface /*, JWTUserInterface */
{
    public static function gareScopeField(): string
    {
        return 'gare';
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['read:User', 'read:Voyage', 'read:Activite'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank()]
    #[Assert\Email()]
    #[Groups(['read:User', 'write:User', 'write:User:profil'])]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[Groups(['read:User'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    #[Assert\Length(minMessage: 'Le nom est obligatoire', min: 1)]
    #[Groups(['read:User', 'write:User', 'write:User:profil', 'read:Voyage', 'read:Activite'])]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Groups(['read:User', 'write:User', 'write:User:profil', 'read:Voyage', 'read:Activite'])]
    #[Assert\NotBlank()]
    #[Assert\Length(minMessage: 'Le prenom est obligatoire', min: 1)] // 'message'..
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    # #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")] -- !!
    #[Groups(['read:User'])]
    private ?Entreprise $entreprise = null;

    /**
     * @var Collection<int, UserRole>
     */
    #[ORM\OneToMany(targetEntity: UserRole::class, mappedBy: 'usere', cascade: ['persist'])]
    #[Groups(['read:User', 'write:User'])]
    private Collection $userRoles;

    /*
        #[Assert\NotBlank(groups: ['write:User'])]
        #[Assert\Length(
            min: 2,
            max: 4096,
            minMessage: "Le mot de passe doit faire au moins {{ limit }} caractères",
            groups: ['write:User']
        )]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            message: 'Le mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial',
            groups: ['write:User']
        )]
    */
    #[Groups(['write:User'])]
    private ?string $plainPassword = null;

    #[Groups(['read:User'])]
    private ?int $entrepriseid = null;

    #[UploadableField(mapping: 'users', fileNameProperty: 'avatar')]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxSizeMessage: 'L\'image ne doit pas dépasser 5Mo',
        mimeTypesMessage: 'Seules les images JPEG, PNG et WEBP sont autorisées'
    )]
    private ?File $file = null;

    #[Groups(['read:User'])]
    private ?string $fileUrl = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['read:User'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, PasswordResetToken>
     */
    #[ORM\OneToMany(targetEntity: PasswordResetToken::class, mappedBy: 'user')]
    private Collection $passwordResetTokens;

    #[ORM\Column(length: 50)]
    #[Groups(['read:User'])]
    private ?string $statut = ReferenceStatus::ACTIF->value;

    #[ORM\Column(nullable: true)]
    private ?bool $isFounder = false;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[Groups(['read:User', 'write:User'])]
    private ?Gare $gare = null;

    public function __construct()
    {
        $this->userRoles = new ArrayCollection();
        $this->passwordResetTokens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;

        return $this;
    }

    /**
     * @return Collection<int, UserRole>
     */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(UserRole $userRole): static
    {
        if (!$this->userRoles->contains($userRole)) {
            $this->userRoles->add($userRole);
            $userRole->setUsere($this);
        }

        return $this;
    }

    public function removeUserRole(UserRole $userRole): static
    {
        if ($this->userRoles->removeElement($userRole)) {
            // set the owning side to null (unless already changed)
            if ($userRole->getUsere() === $this) {
                $userRole->setUsere(null);
            }
        }

        return $this;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }
    /*
        public static function createFromPayload($username, array $payload): User
        {
            $user = new User();
            return $user
                ->setEmail($username)
                ->setRoles($payload['roles'] ?? []) -- Le 'payload' contient le jwt, '??' à cause du système de refresh
                    ->setId($payload['id'] ?? null)
                    ->setEntrepriseid($payload['entrepriseId'] ?? null)
            ;
        }
    */
    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getEntrepriseid(): ?int
    {
        return $this->entrepriseid;
    }

    public function setEntrepriseid(?int $entrepriseid): static
    {
        $this->entrepriseid = $entrepriseid;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile($file): static
    {
        $this->file = $file;

        if($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getFileUrl(): ?string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(?string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return Collection<int, PasswordResetToken>
     */
    public function getPasswordResetTokens(): Collection
    {
        return $this->passwordResetTokens;
    }

    public function addPasswordResetToken(PasswordResetToken $passwordResetToken): static
    {
        if (!$this->passwordResetTokens->contains($passwordResetToken)) {
            $this->passwordResetTokens->add($passwordResetToken);
            $passwordResetToken->setUser($this);
        }

        return $this;
    }

    public function removePasswordResetToken(PasswordResetToken $passwordResetToken): static
    {
        if ($this->passwordResetTokens->removeElement($passwordResetToken)) {
            // set the owning side to null (unless already changed)
            if ($passwordResetToken->getUser() === $this) {
                $passwordResetToken->setUser(null);
            }
        }

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    #[Groups(['read:User'])]
    public function isFounder(): ?bool
    {
        return $this->isFounder;
    }

    public function setIsFounder(?bool $isFounder): static
    {
        $this->isFounder = $isFounder;

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

}

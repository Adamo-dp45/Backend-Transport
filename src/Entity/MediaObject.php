<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\Symfony\Action\NotFoundAction;
use App\Controller\Api\MediaObjectController;
use App\Entity\Interface\EntrepriseOwnedInterface;
use App\Entity\Trait\IdEntrepriseTrait;
use App\Repository\MediaObjectRepository;
use ArrayObject;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute\Uploadable;
use Vich\UploaderBundle\Mapping\Attribute\UploadableField;

/**
 * Un fichier téléversé : image PUBLIQUE (logo, photo d'un personnel, image d'une pièce) ou DOCUMENT
 * PRIVÉ (justificatif d'une dépense — facture, reçu, bulletin de salaire).
 *
 * !! DEUX RÈGLES, corrigées le 28/09/2026. Avant, un média n'appartenait à personne et vivait sous
 * `public/` : qui devinait un identifiant obtenait l'URL, et l'URL donnait le fichier SANS AUCUNE
 * authentification (statique, ou via Glide sur `/media/…`). Une facture ou un bulletin de salaire
 * était donc lisible par n'importe quel compte de n'importe quelle compagnie — puis par n'importe qui.
 *
 *  1. RATTACHEMENT À L'ENTREPRISE (`identreprise`, posé à l'upload) : `EntrepriseScopeExtension`
 *     s'applique à la RÉSOLUTION D'UN IRI, si bien qu'un média d'une autre compagnie ne peut plus être
 *     accroché à une fiche (400, « introuvable ») — ni donc relu à travers elle. La lecture DIRECTE
 *     (`GET /api/media_objects/{id}`) est fermée, comme pour `Permission` : personne ne s'en servait,
 *     le média se lit toujours À TRAVERS la fiche qui le porte.
 *  2. DEUX STOCKAGES. Une image reste publique : elle s'affiche dans des balises `<img>`, sur les apps
 *     et sur les billets, et Glide la redimensionne — la faire passer par
 *     une route authentifiée obligerait le frontend à relayer chaque vignette. Un DOCUMENT, lui, part dans
 *     `public/documents` (mapping `media_prive`), dossier INTERDIT au serveur web par son `.htaccess`,
 *     sous un nom aléatoire de 128 bits (`NomAleatoireNamer`) — la seconde barrière, pour les serveurs
 *     qui ignorent le `.htaccess` (`symfony serve`, Nginx). Il ne se lit que par une route qui applique
 *     les droits de la fiche propriétaire (`GET /api/depenses/{id}/justificatif`). Son `contentUrl` vaut
 *     NULL : il n'a pas d'URL, par construction.
 *
 * La PORTÉE ne se choisit qu'à l'upload (`prive=1`), et chaque stockage a sa propre liste de formats :
 * un PDF ne peut plus atterrir dans `public/`, où il n'avait de toute façon rien à faire (ni `<img>`
 * ni Glide ne savent l'afficher).
 */
#[ORM\Entity(repositoryClass: MediaObjectRepository::class)]
#[Uploadable()]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['media_object:read']],
    types: ['https://schema.org/MediaObject'],
    outputFormats: ['jsonld' => ['application/ld+json']],
    operations: [
        new Get(
            controller: NotFoundAction::class, /*
                - Lecture DIRECTE fermée (même patron que 'Permission') : l'opération ne sert plus qu'à
                  générer et RÉSOUDRE l'IRI. Personne ne la consommait — le frontend et les apps lisent
                  le média à travers la fiche qui le porte — et ouverte, elle laissait parcourir les
                  médias de sa compagnie par identifiant, justificatifs du siège compris
                - La résolution d'un IRI (accrocher un média à une fiche) passe par le PROVIDER, pas par
                  ce contrôleur : elle continue de fonctionner, bornée par 'EntrepriseScopeExtension'
            */
            read: false,
            output: false,
            openapi: new Operation(
                summary: 'hidden'
            )
        ),
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            controller: MediaObjectController::class,
            openapi: new Operation(
                summary: 'Téléversement d’un fichier',
                description: 'Image publique par défaut ; `prive=1` pour un document (justificatif), stocké dans un dossier interdit au serveur web, sans URL',
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
                                    'prive' => [
                                        'type' => 'boolean'
                                    ]
                                ]
                            ]
                        ]
                    ])
                )
            )
        )
    ]
)]
class MediaObject extends EntityBase implements EntrepriseOwnedInterface /*
    - 'EntityBase' pour 'deletedAt' ('EntrepriseScopeExtension' le filtre sur toute entité d'entreprise)
      et 'createdBy' : c'est l'AUTEUR de l'upload qui, seul, peut accrocher un document privé à une
      dépense (cf. 'DepenseProcessor'). Exclu de la corbeille ('CorbeilleRegistry') : un média n'a pas
      d'opération de suppression, il suit la fiche qui le porte
*/
{
    use IdEntrepriseTrait;

    public const FORMATS_IMAGE = ['image/jpeg', 'image/png', 'image/webp'];

    public const FORMATS_DOCUMENT = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['media_object:read'])]
    private ?int $id = null;

    /** NULL pour un document privé : il n'a pas d'URL (cf. `MediaObjectNormalizer`). */
    #[ApiProperty(types: ['https://schema.org/contentUrl'], writable: false)]
    #[Groups(['media_object:read', 'read:Personnel', 'read:Piece', 'read:Entreprise', 'read:Depense'])]
    public ?string $contentUrl = null;

    /** Image PUBLIQUE, sous `public/images/media` : servie en statique et par Glide. */
    #[UploadableField(mapping: 'media_object', fileNameProperty: 'filePath')]
    #[Assert\NotNull(groups: ['public'])]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: self::FORMATS_IMAGE,
        maxSizeMessage: 'Le fichier ne doit pas dépasser 5Mo',
        mimeTypesMessage: 'Seules les images JPEG, PNG et WEBP sont autorisées',
        groups: ['public']
    )]
    public ?File $file = null;

    /**
     * Document PRIVÉ, sous `public/documents` (interdit au serveur web, nom aléatoire) : aucune URL ne
     * l'atteint, seule une route qui applique les droits de la fiche propriétaire le sert. PDF admis — une
     * facture arrive souvent déjà numérisée.
     */
    #[UploadableField(mapping: 'media_prive', fileNameProperty: 'documentPath')]
    #[Assert\NotNull(groups: ['prive'])]
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: self::FORMATS_DOCUMENT,
        maxSizeMessage: 'Le fichier ne doit pas dépasser 5Mo',
        mimeTypesMessage: 'Seuls les fichiers JPEG, PNG, WEBP et PDF sont autorisés',
        groups: ['prive']
    )]
    public ?File $document = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $documentPath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getDocumentPath(): ?string
    {
        return $this->documentPath;
    }

    public function setDocumentPath(?string $documentPath): static
    {
        $this->documentPath = $documentPath;

        return $this;
    }

    /**
     * DÉRIVÉ du stockage, jamais stocké à côté : une colonne `prive` pourrait contredire l'endroit où
     * le fichier se trouve réellement.
     */
    #[Groups(['media_object:read', 'read:Depense'])]
    #[SerializedName('prive')]
    public function isPrive(): bool
    {
        return $this->documentPath !== null;
    }
}

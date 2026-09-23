<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Controller\Api\MediaObjectController;
use App\Repository\MediaObjectRepository;
use ArrayObject;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute\Uploadable;
use Vich\UploaderBundle\Mapping\Attribute\UploadableField;

#[ORM\Entity(repositoryClass: MediaObjectRepository::class)]
#[Uploadable()]
#[ApiResource(
    security: "is_granted('IS_AUTHENTICATED_FULLY')",
    normalizationContext: ['groups' => ['media_object:read']],
    types: ['https://schema.org/MediaObject'],
    outputFormats: ['jsonld' => ['application/ld+json']],
    operations: [
        new Get(),
        new Post(
            inputFormats: ['multipart' => ['multipart/form-data']],
            deserialize: false,
            controller: MediaObjectController::class,
            openapi: new Operation(
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary'
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
class MediaObject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['media_object:read'])]
    private ?int $id = null;

    #[ApiProperty(types: ['https://schema.org/contentUrl'], writable: false)]
    #[Groups(['media_object:read', 'read:Personnel', 'read:Piece', 'read:Entreprise', 'read:Depense'])]
    public ?string $contentUrl = null;

    #[UploadableField(mapping: 'media_object', fileNameProperty: 'filePath')]
    #[Assert\NotNull]
    /*
        'File' et non 'Image' depuis le module DÉPENSES : un justificatif arrive aussi bien en photo
        prise au guichet qu'en FACTURE PDF déjà numérisée, que la contrainte 'Image' refusait. Le
        changement est strictement PERMISSIF — les trois types d'images restent acceptés, le logo
        d'entreprise, la photo d'un personnel et l'image d'une pièce continuent de passer.
    */
    #[Assert\File(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
        maxSizeMessage: 'Le fichier ne doit pas dépasser 5Mo',
        mimeTypesMessage: 'Seuls les fichiers JPEG, PNG, WEBP et PDF sont autorisés'
    )]
    public ?File $file = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }
}

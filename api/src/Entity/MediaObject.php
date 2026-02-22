<?php

namespace App\Entity;

use App\Enum\UploadDirectoryEnum;
use App\Repository\MediaObjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: MediaObjectRepository::class)]
#[Vich\Uploadable]
class MediaObject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Vich\UploadableField(mapping: 'media_object', fileNameProperty: 'filePath')]
    #[Assert\NotNull(groups: ['media_object:create'])]
    #[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
    public ?File $file = null;

    #[ORM\Column(nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 30, enumType: UploadDirectoryEnum::class)]
    #[Assert\NotNull(groups: ['media_object:create'])]
    private UploadDirectoryEnum $directory = UploadDirectoryEnum::PROFILE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

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

    public function getDirectory(): UploadDirectoryEnum
    {
        return $this->directory;
    }

    public function setDirectory(UploadDirectoryEnum $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function setFile(?File $file = null): void
    {
        $this->file = $file;

        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}

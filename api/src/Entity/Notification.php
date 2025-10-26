<?php
// api/src/Entity/Notification.php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
        ),
        new Get(
            security: "is_granted('NOTIFICATION_VIEW', object)",
        ),
        new Patch(
            security: "is_granted('NOTIFICATION_UPDATE', object)",
        ),
    ],
    normalizationContext: ['groups' => ['notification:read']],
    denormalizationContext: ['groups' => ['notification:update']],
)]
class Notification
{
    // Types de notifications
    public const TYPE_NEW_MESSAGE = 'new_message';
    public const TYPE_SESSION_CONFIRMED = 'session_confirmed';
    public const TYPE_SESSION_CANCELLED = 'session_cancelled';
    public const TYPE_SESSION_COMPLETED = 'session_completed';
    public const TYPE_NEW_REVIEW = 'new_review';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['notification:read'])]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Groups(['notification:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $content = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['notification:read'])]
    private ?string $link = null;

    #[ORM\Column]
    #[Groups(['notification:read', 'notification:update'])]
    private bool $isRead = false;

    #[ORM\Column(nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters/Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        // Quand on marque comme lu, set readAt automatiquement
        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}

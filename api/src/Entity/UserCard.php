<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\UserCardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserCardRepository::class)]
#[ORM\Table(name: 'user_card')]
#[ORM\UniqueConstraint(
    name: 'uniq_user_card_user_card',
    columns: ['user_id', 'card_id']
)]
#[ORM\Index(name: 'idx_user_card_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_user_card_card', columns: ['card_id'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class UserCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userCards')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Card::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Card $card = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $obtainedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $seenAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $meta = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCard(): ?Card
    {
        return $this->card;
    }

    public function setCard(Card $card): static
    {
        $this->card = $card;

        return $this;
    }

    public function getObtainedAt(): ?\DateTimeImmutable
    {
        return $this->obtainedAt;
    }

    public function setObtainedAt(\DateTimeImmutable $obtainedAt): static
    {
        $this->obtainedAt = $obtainedAt;

        return $this;
    }

    public function getSeenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function setSeenAt(?\DateTimeImmutable $seenAt): static
    {
        $this->seenAt = $seenAt;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function setMeta(?array $meta): static
    {
        $this->meta = $meta;

        return $this;
    }

    #[ORM\PrePersist]
    public function setDefaultObtainedAt(): void
    {
        if ($this->obtainedAt === null) {
            $this->obtainedAt = new \DateTimeImmutable();
        }
    }
}

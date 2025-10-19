<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\UserSkillRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserSkillRepository::class)]
#[ORM\Table(name: 'user_skill')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(
    name: 'unique_user_skill_type',
    columns: ['owner_id', 'skill_id', 'type']
)]
#[UniqueEntity(
    fields: ['owner', 'skill', 'type'],
    message: 'This user already has this skill with this type'
)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "Only admins can create user skills directly."
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "Only admins can delete user skills directly."
        ),
    ],
    normalizationContext: ['groups' => ['user_skill:read']],
    denormalizationContext: ['groups' => ['user_skill:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'owner' => 'exact',
    'skill' => 'exact',
    'type' => 'exact',
    'level' => 'exact'
])]
class UserSkill
{
    public const TYPE_TEACH = 'teach';
    public const TYPE_LEARN = 'learn';

    public const LEVEL_BEGINNER = 'beginner';
    public const LEVEL_INTERMEDIATE = 'intermediate';
    public const LEVEL_ADVANCED = 'advanced';
    public const LEVEL_EXPERT = 'expert';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user_skill:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userSkills')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The user cannot be null')]
    #[Groups(['user_skill:read', 'user_skill:write'])]
    private ?User $owner = null;

    #[ORM\ManyToOne(inversedBy: 'userSkills')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The skill cannot be null')]
    #[Groups(['user_skill:read', 'user_skill:write', 'user:read'])]
    private ?Skill $skill = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'The type cannot be blank')]
    #[Assert\Choice(
        choices: [self::TYPE_TEACH, self::TYPE_LEARN],
        message: 'The type must be either "teach" or "learn"'
    )]
    #[Groups(['user_skill:read', 'user_skill:write'])]
    private ?string $type = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(
        choices: [
            self::LEVEL_BEGINNER,
            self::LEVEL_INTERMEDIATE,
            self::LEVEL_ADVANCED,
            self::LEVEL_EXPERT
        ],
        message: 'The level must be one of: beginner, intermediate, advanced, expert'
    )]
    #[Groups(['user_skill:read', 'user_skill:write'])]
    private ?string $level = null;

    #[ORM\Column]
    #[Groups(['user_skill:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    // === Lifecycle Callbacks ===

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // === Getters / Setters ===

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;
        return $this;
    }

    public function getSkill(): ?Skill
    {
        return $this->skill;
    }

    public function setSkill(?Skill $skill): static
    {
        $this->skill = $skill;
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

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}

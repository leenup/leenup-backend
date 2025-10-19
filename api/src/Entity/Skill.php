<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(
    fields: ['title', 'category'],
    message: 'This skill already exists in this category'
)]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(),
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Patch(),
        new Delete(),
    ],
    normalizationContext: ['groups' => ['skill:read']],
    denormalizationContext: ['groups' => ['skill:write']],
    security: "is_granted('ROLE_ADMIN')",
    securityMessage: 'Only admins can access this resource.',
)]
#[ApiFilter(SearchFilter::class, properties: [
    'category' => 'exact',
    'title' => 'partial'
])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'title', 'createdAt'])]
class Skill
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['skill:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'The title cannot be blank')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'The title must be at least {{ limit }} characters long',
        maxMessage: 'The title cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['skill:read', 'skill:write', 'category:read'])]
    private ?string $title = null;

    #[ORM\ManyToOne(inversedBy: 'skills')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The category cannot be null')]
    #[Groups(['skill:read', 'skill:write'])]
    private ?Category $category = null;

    #[ORM\Column]
    #[Groups(['skill:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['skill:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, UserSkill>
     */
    #[ORM\OneToMany(targetEntity: UserSkill::class, mappedBy: 'skill', orphanRemoval: true)]
    #[Groups(['skill:read'])]
    private Collection $userSkills;

    public function __construct()
    {
        $this->userSkills = new ArrayCollection();
    }

    // === Lifecycle Callbacks ===

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // === Getters / Setters ===

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, UserSkill>
     */
    public function getUserSkills(): Collection
    {
        return $this->userSkills;
    }

    public function addUserSkill(UserSkill $userSkill): static
    {
        if (!$this->userSkills->contains($userSkill)) {
            $this->userSkills->add($userSkill);
            $userSkill->setSkill($this);
        }

        return $this;
    }

    public function removeUserSkill(UserSkill $userSkill): static
    {
        if ($this->userSkills->removeElement($userSkill)) {
            // set the owning side to null (unless already changed)
            if ($userSkill->getSkill() === $this) {
                $userSkill->setSkill(null);
            }
        }

        return $this;
    }
}

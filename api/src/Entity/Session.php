<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Repository\SessionRepository;
use App\State\Processor\Session\SessionCreateProcessor;
use App\State\Processor\Session\SessionStatusProcessor;
use App\State\Processor\Session\SessionUpdateProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SessionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: SessionCreateProcessor::class,
        ),
        new Get(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Patch(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: SessionUpdateProcessor::class,
        ),
        new Patch(
            uriTemplate: '/sessions/{id}/confirm',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: false,
            processor: SessionStatusProcessor::class,
            extraProperties: ['target_status' => Session::STATUS_CONFIRMED],
        ),
        new Patch(
            uriTemplate: '/sessions/{id}/complete',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: false,
            processor: SessionStatusProcessor::class,
            extraProperties: ['target_status' => Session::STATUS_COMPLETED],
        ),
        new Patch(
            uriTemplate: '/sessions/{id}/cancel',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            input: false,
            processor: SessionStatusProcessor::class,
            extraProperties: ['target_status' => Session::STATUS_CANCELLED],
        ),
        new Delete(
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
    ],
    normalizationContext: ['groups' => ['session:read']],
    denormalizationContext: ['groups' => ['session:write']],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'mentor' => 'exact',
    'student' => 'exact',
    'skill' => 'exact',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['scheduledAt', 'createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'scheduledAt', 'createdAt'])]
class Session
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['session:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessionsAsMentor')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The mentor cannot be null')]
    #[Groups(['session:read', 'session:write'])]
    private ?User $mentor = null;

    #[ORM\ManyToOne(inversedBy: 'sessionsAsStudent')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['session:read', 'session:write'])]
    private ?User $student = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'The skill cannot be null')]
    #[Groups(['session:read', 'session:write'])]
    private ?Skill $skill = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'The status cannot be blank')]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED
        ],
        message: 'The status must be one of: pending, confirmed, cancelled, completed'
    )]
    #[Groups(['session:read', 'session:write'])]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    #[Assert\NotNull(message: 'The scheduled date cannot be null')]
    #[Groups(['session:read', 'session:write'])]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'The duration cannot be null')]
    #[Assert\Positive(message: 'The duration must be a positive number')]
    #[Groups(['session:read', 'session:write'])]
    private ?int $duration = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'The location cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['session:read', 'session:write'])]
    private ?string $location = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'The notes cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['session:read', 'session:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['session:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['session:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'session', orphanRemoval: true)]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    // === Lifecycle Callbacks ===

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();

        if ($this->status === null) {
            $this->status = self::STATUS_PENDING;
        }
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

    public function getMentor(): ?User
    {
        return $this->mentor;
    }

    public function setMentor(?User $mentor): static
    {
        $this->mentor = $mentor;
        return $this;
    }

    public function getStudent(): ?User
    {
        return $this->student;
    }

    public function setStudent(?User $student): static
    {
        $this->student = $student;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
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
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setSession($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getSession() === $this) {
                $review->setSession(null);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
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
use App\Repository\UserRepository;
use App\State\UserPasswordHasher;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use')]
#[ApiFilter(SearchFilter::class, properties: [
    'email'     => 'partial',
    'firstName' => 'partial',
    'lastName'  => 'partial',
])]
#[ApiFilter(BooleanFilter::class, properties: [
    'isMentor',
    'isActive',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'id',
    'email',
    'firstName',
    'lastName',
    'createdAt',
    'updatedAt',
    'averageRating',
    'isMentor',
])]
#[ApiFilter(DateFilter::class, properties: [
    'createdAt',
    'updatedAt',
    'birthdate',
])]
#[ApiResource(
    operations: [
        new GetCollection(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can list users.'
        ),
        new Get(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can view user details.'
        ),
        new Patch(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: 'Only admins can update users.',
            securityPostDenormalize: "!('ROLE_ADMIN' in previous_object.getRoles())",
            securityPostDenormalizeMessage: "Admins cannot modify admin users (including themselves).",
            processor: UserPasswordHasher::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN')",
            securityMessage: "Only admins can delete users.",
            securityPostDenormalize: "!('ROLE_ADMIN' in previous_object.getRoles())",
            securityPostDenormalizeMessage: "Admins cannot delete admin users (including themselves).",
        ),
        new Post(
            uriTemplate: '/register',
            security: "is_granted('PUBLIC_ACCESS')",
            securityPostDenormalize: "is_granted('ROLE_ADMIN') or !object.hasRole('ROLE_ADMIN')",
            securityPostDenormalizeMessage: "Only admins can assign admin roles.",
            validationContext: ['groups' => ['Default', 'user:create']],
            processor: UserPasswordHasher::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:create', 'user:update:admin']],
    security: "is_granted('ROLE_ADMIN')",
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:create', 'user:update', 'user:update:admin'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['user:read', 'user:create', 'user:update:admin'])]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column]
    private ?string $password = null;

    #[Assert\NotBlank(groups: ['user:create'])]
    #[Groups(['user:create', 'user:update', 'user:update:admin'])]
    private ?string $plainPassword = null;

    // ========== NOUVEAUX CHAMPS MVP ==========

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'First name must be at least {{ limit }} characters long',
        maxMessage: 'First name cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Last name must be at least {{ limit }} characters long',
        maxMessage: 'Last name cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Url]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Bio cannot be longer than {{ limit }} characters'
    )]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $location = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $timezone = 'Europe/Paris';

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $locale = 'fr';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Assert\LessThan('today', message: 'Birthdate must be in the past.')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?\DateTimeImmutable $birthdate = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('array')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?array $languages = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['visio', 'chat', 'audio'],
        message: 'Invalid exchange format.'
    )]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?string $exchangeFormat = null;

    /**
     * @var string[]|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Type('array')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private ?array $learningStyles = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:read', 'user:create', 'user:update'])]
    private bool $isMentor = false;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, UserSkill>
     */
    #[ORM\OneToMany(targetEntity: UserSkill::class, mappedBy: 'owner', cascade: ['remove'], orphanRemoval: true)]
    private Collection $userSkills;

    /**
     * @var Collection<int, Session>
     */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'mentor')]
    private Collection $sessionsAsMentor;

    /**
     * @var Collection<int, Session>
     */
    #[ORM\OneToMany(targetEntity: Session::class, mappedBy: 'student')]
    private Collection $sessionsAsStudent;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'reviewer', orphanRemoval: true)]
    private Collection $reviews;

    /**
     * @var Collection<int, UserCard>
     */
    #[ORM\OneToMany(
        targetEntity: UserCard::class,
        mappedBy: 'user',
    )]
    private Collection $userCards;

    /**
     * @var string|null
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $averageRating = null;

    public function __construct()
    {
        $this->userSkills = new ArrayCollection();
        $this->roles = ['ROLE_USER'];
        $this->sessionsAsMentor = new ArrayCollection();
        $this->sessionsAsStudent = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->isMentor = false;
        $this->isActive = true;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ========== GETTERS/SETTERS NOUVEAUX CHAMPS ==========

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function setAvatarUrl(?string $avatarUrl): static
    {
        $this->avatarUrl = $avatarUrl;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

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

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getBirthdate(): ?\DateTimeImmutable
    {
        return $this->birthdate;
    }

    public function setBirthdate(?\DateTimeImmutable $birthdate): static
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getLanguages(): ?array
    {
        return $this->languages;
    }

    /**
     * @param string[]|null $languages
     */
    public function setLanguages(?array $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function getExchangeFormat(): ?string
    {
        return $this->exchangeFormat;
    }

    public function setExchangeFormat(?string $exchangeFormat): static
    {
        $this->exchangeFormat = $exchangeFormat;

        return $this;
    }

    /**
     * @return string[]|null
     */
    public function getLearningStyles(): ?array
    {
        return $this->learningStyles;
    }

    /**
     * @param string[]|null $learningStyles
     */
    public function setLearningStyles(?array $learningStyles): static
    {
        $this->learningStyles = $learningStyles;

        return $this;
    }

    public function getIsMentor(): bool
    {
        return $this->isMentor;
    }

    public function setIsMentor(bool $isMentor): static
    {
        $this->isMentor = $isMentor;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
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
            $userSkill->setOwner($this);
        }

        return $this;
    }

    public function removeUserSkill(UserSkill $userSkill): static
    {
        if ($this->userSkills->removeElement($userSkill)) {
            if ($userSkill->getOwner() === $this) {
                $userSkill->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessionsAsMentor(): Collection
    {
        return $this->sessionsAsMentor;
    }

    public function addSessionAsMentor(Session $session): static
    {
        if (!$this->sessionsAsMentor->contains($session)) {
            $this->sessionsAsMentor->add($session);
            $session->setMentor($this);
        }

        return $this;
    }

    public function removeSessionAsMentor(Session $session): static
    {
        if ($this->sessionsAsMentor->removeElement($session)) {
            if ($session->getMentor() === $this) {
                $session->setMentor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Session>
     */
    public function getSessionsAsStudent(): Collection
    {
        return $this->sessionsAsStudent;
    }

    public function addSessionAsStudent(Session $session): static
    {
        if (!$this->sessionsAsStudent->contains($session)) {
            $this->sessionsAsStudent->add($session);
            $session->setStudent($this);
        }

        return $this;
    }

    public function removeSessionAsStudent(Session $session): static
    {
        if ($this->sessionsAsStudent->removeElement($session)) {
            if ($session->getStudent() === $this) {
                $session->setStudent(null);
            }
        }

        return $this;
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
            $review->setReviewer($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getReviewer() === $this) {
                $review->setReviewer(null);
            }
        }

        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }
}

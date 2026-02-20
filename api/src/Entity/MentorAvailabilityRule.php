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
use App\Repository\MentorAvailabilityRuleRepository;
use App\State\Processor\MentorAvailabilityRule\MentorAvailabilityRuleCreateProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MentorAvailabilityRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/mentor_availability_rules',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Post(
            uriTemplate: '/mentor_availability_rules',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MentorAvailabilityRuleCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/mentor_availability_rules/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
        ),
        new Patch(
            uriTemplate: '/mentor_availability_rules/{id}',
            security: "is_granted('ROLE_ADMIN') or object.getMentor() == user",
        ),
        new Delete(
            uriTemplate: '/mentor_availability_rules/{id}',
            security: "is_granted('ROLE_ADMIN') or object.getMentor() == user",
        ),
    ],
    normalizationContext: ['groups' => ['availability:read']],
    denormalizationContext: ['groups' => ['availability:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['mentor' => 'exact', 'type' => 'exact', 'isActive' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['id', 'dayOfWeek', 'startsAt', 'endsAt', 'createdAt'])]
class MentorAvailabilityRule
{
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_ONE_SHOT = 'one_shot';
    public const TYPE_EXCLUSION = 'exclusion';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['availability:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mentorAvailabilityRules')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['availability:read'])]
    private ?User $mentor = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::TYPE_WEEKLY, self::TYPE_ONE_SHOT, self::TYPE_EXCLUSION])]
    #[Groups(['availability:read', 'availability:write'])]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 7)]
    #[Groups(['availability:read', 'availability:write'])]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups(['availability:read', 'availability:write'])]
    private ?\DateTimeImmutable $startTime = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    #[Groups(['availability:read', 'availability:write'])]
    private ?\DateTimeImmutable $endTime = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['availability:read', 'availability:write'])]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['availability:read', 'availability:write'])]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 64)]
    #[Groups(['availability:read', 'availability:write'])]
    private string $timezone = 'Europe/Paris';

    #[ORM\Column]
    #[Groups(['availability:read', 'availability:write'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['availability:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['availability:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeImmutable $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}

<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Processor\Profile\MyMentorAvailabilityCreateProcessor;
use App\State\Processor\Profile\MyMentorAvailabilityDeleteProcessor;
use App\State\Processor\Profile\MyMentorAvailabilityUpdateProcessor;
use App\State\Provider\Profile\MyMentorAvailabilityProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MyMentorAvailability',
    operations: [
        new GetCollection(
            uriTemplate: '/me/availability',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityProvider::class,
        ),
        new Post(
            uriTemplate: '/me/availability',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MyMentorAvailabilityCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/me/availability/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityProvider::class,
        ),
        new Patch(
            uriTemplate: '/me/availability/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityProvider::class,
            processor: MyMentorAvailabilityUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/me/availability/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityProvider::class,
            processor: MyMentorAvailabilityDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_availability:read']],
    denormalizationContext: ['groups' => ['my_availability:write']],
)]
class MyMentorAvailability
{
    #[Groups(['my_availability:read'])]
    public ?int $id = null;

    #[Assert\NotNull(message: 'The day of week cannot be null')]
    #[Assert\Range(min: 0, max: 6, notInRangeMessage: 'The day of week must be between 0 and 6')]
    #[Groups(['my_availability:read', 'my_availability:write'])]
    public ?int $dayOfWeek = null;

    #[Assert\NotNull(message: 'The start time cannot be null')]
    #[Groups(['my_availability:read', 'my_availability:write'])]
    public ?\DateTimeImmutable $startTime = null;

    #[Assert\NotNull(message: 'The end time cannot be null')]
    #[Groups(['my_availability:read', 'my_availability:write'])]
    public ?\DateTimeImmutable $endTime = null;

    #[Groups(['my_availability:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['my_availability:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}

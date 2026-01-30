<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Entity\MentorAvailabilityException;
use App\State\Processor\Profile\MyMentorAvailabilityExceptionCreateProcessor;
use App\State\Processor\Profile\MyMentorAvailabilityExceptionDeleteProcessor;
use App\State\Processor\Profile\MyMentorAvailabilityExceptionUpdateProcessor;
use App\State\Provider\Profile\MyMentorAvailabilityExceptionProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'MyMentorAvailabilityException',
    operations: [
        new GetCollection(
            uriTemplate: '/me/availability-exceptions',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityExceptionProvider::class,
        ),
        new Post(
            uriTemplate: '/me/availability-exceptions',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MyMentorAvailabilityExceptionCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/me/availability-exceptions/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityExceptionProvider::class,
        ),
        new Patch(
            uriTemplate: '/me/availability-exceptions/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityExceptionProvider::class,
            processor: MyMentorAvailabilityExceptionUpdateProcessor::class,
        ),
        new Delete(
            uriTemplate: '/me/availability-exceptions/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyMentorAvailabilityExceptionProvider::class,
            processor: MyMentorAvailabilityExceptionDeleteProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_availability_exception:read']],
    denormalizationContext: ['groups' => ['my_availability_exception:write']],
)]
class MyMentorAvailabilityException
{
    #[Groups(['my_availability_exception:read'])]
    public ?int $id = null;

    #[Assert\NotNull(message: 'The date cannot be null')]
    #[Groups(['my_availability_exception:read', 'my_availability_exception:write'])]
    public ?\DateTimeImmutable $date = null;

    #[Groups(['my_availability_exception:read', 'my_availability_exception:write'])]
    public ?\DateTimeImmutable $startTime = null;

    #[Groups(['my_availability_exception:read', 'my_availability_exception:write'])]
    public ?\DateTimeImmutable $endTime = null;

    #[Assert\Choice(
        choices: [MentorAvailabilityException::TYPE_UNAVAILABLE, MentorAvailabilityException::TYPE_OVERRIDE],
        message: 'Invalid exception type'
    )]
    #[Groups(['my_availability_exception:read', 'my_availability_exception:write'])]
    public ?string $type = MentorAvailabilityException::TYPE_UNAVAILABLE;

    #[Groups(['my_availability_exception:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['my_availability_exception:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}

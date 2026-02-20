<?php

namespace App\ApiResource\Mentor;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\Provider\Mentor\MentorAvailableSlotProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MentorAvailableSlot',
    operations: [
        new GetCollection(
            uriTemplate: '/mentors/{id}/available-slots',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MentorAvailableSlotProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['mentor_available_slot:read']],
)]
class MentorAvailableSlot
{
    #[ApiProperty(identifier: true)]
    #[Groups(['mentor_available_slot:read'])]
    public ?string $id = null;

    #[Groups(['mentor_available_slot:read'])]
    public ?\DateTimeImmutable $startAt = null;

    #[Groups(['mentor_available_slot:read'])]
    public ?\DateTimeImmutable $endAt = null;

    #[Groups(['mentor_available_slot:read'])]
    public ?int $duration = null;
}

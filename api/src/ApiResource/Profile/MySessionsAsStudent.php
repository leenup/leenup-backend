<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Skill;
use App\Entity\User;
use App\State\Provider\Profile\MySessionsAsStudentProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MySessionAsStudent',
    operations: [
        new GetCollection(
            uriTemplate: '/me/sessions/as-student',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MySessionsAsStudentProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_session:read']],
)]
class MySessionsAsStudent
{
    #[Groups(['my_session:read'])]
    public ?int $id = null;

    #[Groups(['my_session:read'])]
    public ?User $mentor = null;

    #[Groups(['my_session:read'])]
    public ?Skill $skill = null;

    #[Groups(['my_session:read'])]
    public ?string $status = null;

    #[Groups(['my_session:read'])]
    public ?\DateTimeImmutable $scheduledAt = null;

    #[Groups(['my_session:read'])]
    public ?int $duration = null;

    #[Groups(['my_session:read'])]
    public ?string $location = null;

    #[Groups(['my_session:read'])]
    public ?string $notes = null;

    #[Groups(['my_session:read'])]
    public ?\DateTimeImmutable $createdAt = null;
}

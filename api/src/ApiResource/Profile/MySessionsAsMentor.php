<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Session;
use App\Entity\Skill;
use App\Entity\User;
use App\State\Provider\Profile\MySessionsAsMentorProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MySessionAsMentor',
    operations: [
        new GetCollection(
            uriTemplate: '/me/sessions/as-mentor',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MySessionsAsMentorProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_session:read']],
)]
class MySessionsAsMentor
{
    #[Groups(['my_session:read'])]
    public ?int $id = null;

    #[Groups(['my_session:read'])]
    public ?User $student = null;

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

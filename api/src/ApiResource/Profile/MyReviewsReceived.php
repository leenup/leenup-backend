<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Session;
use App\Entity\User;
use App\State\Provider\Profile\MyReviewsReceivedProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MyReviewReceived',
    operations: [
        new GetCollection(
            uriTemplate: '/me/reviews/received',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyReviewsReceivedProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_review:read']],
)]
class MyReviewsReceived
{
    #[Groups(['my_review:read'])]
    public ?int $id = null;

    #[Groups(['my_review:read'])]
    public ?Session $session = null;

    #[Groups(['my_review:read'])]
    public ?User $reviewer = null;

    #[Groups(['my_review:read'])]
    public ?int $rating = null;

    #[Groups(['my_review:read'])]
    public ?string $comment = null;

    #[Groups(['my_review:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['my_review:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}

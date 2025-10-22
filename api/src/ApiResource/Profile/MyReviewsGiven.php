<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\Session;
use App\State\Provider\Profile\MyReviewsGivenProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MyReviewGiven',
    operations: [
        new GetCollection(
            uriTemplate: '/me/reviews/given',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyReviewsGivenProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_review:read']],
)]
class MyReviewsGiven
{
    #[Groups(['my_review:read'])]
    public ?int $id = null;

    #[Groups(['my_review:read'])]
    public ?Session $session = null;

    #[Groups(['my_review:read'])]
    public ?int $rating = null;

    #[Groups(['my_review:read'])]
    public ?string $comment = null;

    #[Groups(['my_review:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['my_review:read'])]
    public ?\DateTimeImmutable $updatedAt = null;
}

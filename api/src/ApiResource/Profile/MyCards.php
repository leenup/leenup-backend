<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use App\State\Provider\Profile\MyCardsProvider;

#[ApiResource(
    shortName: 'MyCard',
    operations: [
        new GetCollection(
            uriTemplate: '/me/cards',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyCardsProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_card:read']],
)]
class MyCards
{
    #[Groups(['my_card:read'])]
    public ?int $id = null;

    #[Groups(['my_card:read'])]
    public ?int $cardId = null;

    #[Groups(['my_card:read'])]
    public ?string $code = null;

    #[Groups(['my_card:read'])]
    public ?string $family = null;

    #[Groups(['my_card:read'])]
    public ?string $title = null;

    #[Groups(['my_card:read'])]
    public ?string $subtitle = null;

    #[Groups(['my_card:read'])]
    public ?string $description = null;

    #[Groups(['my_card:read'])]
    public ?string $category = null;

    #[Groups(['my_card:read'])]
    public ?int $level = null;

    #[Groups(['my_card:read'])]
    public ?string $imageUrl = null;

    #[Groups(['my_card:read'])]
    public ?array $conditions = null;

    #[Groups(['my_card:read'])]
    public ?bool $isActive = null;

    #[Groups(['my_card:read'])]
    public ?\DateTimeImmutable $obtainedAt = null;

    #[Groups(['my_card:read'])]
    public ?\DateTimeImmutable $seenAt = null;

    #[Groups(['my_card:read'])]
    public ?string $source = null;

    #[Groups(['my_card:read'])]
    public ?array $meta = null;
}

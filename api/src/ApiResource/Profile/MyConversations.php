<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Entity\User;
use App\State\Provider\Profile\MyConversationsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'MyConversation',
    operations: [
        new GetCollection(
            uriTemplate: '/me/conversations',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyConversationsProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_conversation:read']],
)]
class MyConversations
{
    #[Groups(['my_conversation:read'])]
    public ?int $id = null;

    #[Groups(['my_conversation:read'])]
    public ?User $otherParticipant = null;

    #[Groups(['my_conversation:read'])]
    public ?string $lastMessage = null;

    #[Groups(['my_conversation:read'])]
    public ?\DateTimeImmutable $lastMessageAt = null;

    #[Groups(['my_conversation:read'])]
    public ?int $unreadCount = null;

    #[Groups(['my_conversation:read'])]
    public ?\DateTimeImmutable $createdAt = null;
}

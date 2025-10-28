<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use App\State\Processor\Profile\MyNotificationsUpdateProcessor;
use App\State\Provider\Profile\MyNotificationsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Ressource API pour les notifications de l'utilisateur connecté
 */
#[ApiResource(
    shortName: 'MyNotification',
    operations: [
        new GetCollection(
            uriTemplate: '/me/notifications',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyNotificationsProvider::class,
        ),
        new Get(
            uriTemplate: '/me/notifications/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyNotificationsProvider::class,
        ),
        new Patch(
            uriTemplate: '/me/notifications/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyNotificationsProvider::class,
            processor: MyNotificationsUpdateProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_notification:read']],
    denormalizationContext: ['groups' => ['my_notification:update']],
)]
class MyNotifications
{
    #[Groups(['my_notification:read'])]
    public ?int $id = null;

    #[Groups(['my_notification:read'])]
    public ?string $type = null;

    #[Groups(['my_notification:read'])]
    public ?string $title = null;

    #[Groups(['my_notification:read'])]
    public ?string $content = null;

    #[Groups(['my_notification:read'])]
    public ?string $link = null;

    #[Groups(['my_notification:read', 'my_notification:update'])]
    public ?bool $isRead = null;

    #[Groups(['my_notification:read'])]
    public ?\DateTimeImmutable $readAt = null;

    #[Groups(['my_notification:read'])]
    public ?\DateTimeImmutable $createdAt = null;
}

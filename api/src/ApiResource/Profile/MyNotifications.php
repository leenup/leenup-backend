<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Processor\Profile\MyNotificationsMarkAllReadProcessor;
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
        new Post(
            uriTemplate: '/me/notifications/mark-all-read',
            status: 200,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            output: ['count' => 'int', 'message' => 'string'],
            read: false,
            serialize: false,
            processor: MyNotificationsMarkAllReadProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_notification:read']],
    denormalizationContext: ['groups' => ['my_notification:update']],
)]
#[ApiFilter(BooleanFilter::class, properties: ['isRead'])]
#[ApiFilter(SearchFilter::class, properties: ['type' => 'exact'])]
#[ApiFilter(DateFilter::class, properties: ['createdAt'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt', 'readAt'], arguments: ['orderParameterName' => 'order'])]
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

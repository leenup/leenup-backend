<?php
// api/src/Factory/NotificationFactory.php

namespace App\Factory;

use App\Entity\Notification;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Notification>
 */
final class NotificationFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    public static function class(): string
    {
        return Notification::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'user' => UserFactory::new(),
            'type' => self::faker()->randomElement([
                Notification::TYPE_NEW_MESSAGE,
                Notification::TYPE_SESSION_CONFIRMED,
                Notification::TYPE_SESSION_CANCELLED,
                Notification::TYPE_SESSION_COMPLETED,
                Notification::TYPE_NEW_REVIEW,
            ]),
            'title' => self::faker()->sentence(6),
            'content' => self::faker()->optional(0.7)->sentence(12),
            'link' => self::faker()->optional(0.5)->randomElement([
                '/sessions/1',
                '/messages/1',
                '/reviews/1',
            ]),
            'isRead' => self::faker()->boolean(30), // 30% de chance d'être lu
        ];
    }

    protected function initialize(): static
    {
        return $this;
    }
}

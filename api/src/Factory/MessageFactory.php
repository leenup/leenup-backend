<?php

namespace App\Factory;

use App\Entity\Message;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Message>
 */
final class MessageFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Message::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'conversation' => ConversationFactory::new(),
            'sender' => UserFactory::new(),
            'content' => self::faker()->sentence(10),
            'read' => self::faker()->boolean(60),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}

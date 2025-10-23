<?php

namespace App\Factory;

use App\Entity\Conversation;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Conversation>
 */
final class ConversationFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Conversation::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'participant1' => UserFactory::new(),
            'participant2' => UserFactory::new(),
            'session' => self::faker()->optional(0.5)->passthrough(SessionFactory::new()),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}

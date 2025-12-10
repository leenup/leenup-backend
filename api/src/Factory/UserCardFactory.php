<?php

namespace App\Factory;

use App\Entity\UserCard;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserCard>
 */
final class UserCardFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return UserCard::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'user' => UserFactory::new(),   // sera résolu par Foundry
            'card' => CardFactory::new(),   // idem
            'obtainedAt' => new \DateTimeImmutable(),
            'seenAt' => null,
            'source' => 'auto',
            'meta' => null,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(UserCard $userCard): void {})
            ;
    }
}

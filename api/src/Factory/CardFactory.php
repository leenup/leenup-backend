<?php

namespace App\Factory;

use App\Entity\Card;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Card>
 */
final class CardFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Card::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'code' => self::faker()->unique()->lexify('card_????'),
            'family' => self::faker()->randomElement([
                'mentor_sessions',
                'student_sessions',
                'engagement',
            ]),
            'title' => self::faker()->sentence(3),
            'subtitle' => self::faker()->boolean(60) ? self::faker()->sentence(6) : null,
            'description' => self::faker()->boolean(50) ? self::faker()->paragraph() : null,
            'category' => self::faker()->randomElement([
                'mentoring',
                'learning',
                'community',
                'achievement',
            ]),
            'level' => self::faker()->numberBetween(1, 3),
            'imageUrl' => '/images/cards/' . self::faker()->lexify('card_????') . '.png',
            'conditions' => [
                'type' => 'sessions_given',
                'operator' => '>=',
                'value' => self::faker()->numberBetween(1, 20),
            ],
            'isActive' => true,
            'createdAt' => new \DateTimeImmutable(),
            'updatedAt' => null,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Card $card): void {})
            ;
    }
}

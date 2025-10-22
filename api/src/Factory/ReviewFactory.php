<?php

namespace App\Factory;

use App\Entity\Review;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Review>
 */
final class ReviewFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Review::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'session' => SessionFactory::new(),
            'reviewer' => UserFactory::new(),
            'rating' => self::faker()->numberBetween(1, 5),
            'comment' => self::faker()->optional(0.7)->paragraph(2),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}

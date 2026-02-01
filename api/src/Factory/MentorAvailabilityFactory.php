<?php

namespace App\Factory;

use App\Entity\MentorAvailability;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<MentorAvailability>
 */
final class MentorAvailabilityFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return MentorAvailability::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'mentor' => lazy(fn() => UserFactory::randomOrCreate()),
            'dayOfWeek' => self::faker()->numberBetween(0, 6),
            'startTime' => new \DateTimeImmutable('09:00'),
            'endTime' => new \DateTimeImmutable('12:00'),
        ];
    }
}

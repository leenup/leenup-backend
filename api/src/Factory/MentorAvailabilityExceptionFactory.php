<?php

namespace App\Factory;

use App\Entity\MentorAvailabilityException;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<MentorAvailabilityException>
 */
final class MentorAvailabilityExceptionFactory extends PersistentObjectFactory
{
    #[\Override]
    public static function class(): string
    {
        return MentorAvailabilityException::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'mentor' => lazy(fn() => UserFactory::randomOrCreate()),
            'date' => new \DateTimeImmutable('today'),
            'startTime' => new \DateTimeImmutable('09:00'),
            'endTime' => new \DateTimeImmutable('12:00'),
            'type' => MentorAvailabilityException::TYPE_UNAVAILABLE,
        ];
    }
}

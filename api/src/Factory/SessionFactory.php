<?php

namespace App\Factory;

use App\Entity\Session;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<Session>
 */
final class SessionFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Session::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        $scheduledAt = self::faker()->dateTimeBetween('now', '+3 months');

        return [
            'mentor' => lazy(fn() => UserFactory::randomOrCreate()),
            'student' => lazy(fn() => UserFactory::randomOrCreate()),
            'skill' => lazy(fn() => SkillFactory::randomOrCreate()),
            'status' => self::faker()->randomElement([
                Session::STATUS_PENDING,
                Session::STATUS_CONFIRMED,
                Session::STATUS_CANCELLED,
                Session::STATUS_COMPLETED
            ]),
            'scheduledAt' => \DateTimeImmutable::createFromMutable($scheduledAt),
            'duration' => self::faker()->randomElement([30, 60, 90, 120]),
            'location' => self::faker()->optional(0.7)->randomElement([
                'Zoom',
                'Google Meet',
                'Microsoft Teams',
                'Discord',
                'En personne - Paris',
                'En personne - Lyon',
                'Skype',
            ]),
            'notes' => self::faker()->optional(0.5)->paragraph(2),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}

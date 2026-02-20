<?php

namespace App\Factory;

use App\Entity\MentorAvailabilityRule;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<MentorAvailabilityRule>
 */
final class MentorAvailabilityRuleFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return MentorAvailabilityRule::class;
    }

    protected function defaults(): array|callable
    {
        $startsAt = new \DateTimeImmutable('now');

        return [
            'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
            'startsAt' => $startsAt,
            'endsAt' => $startsAt->modify('+3 months'),
            'timezone' => 'Europe/Paris',
            'isActive' => true,
        ];
    }
}

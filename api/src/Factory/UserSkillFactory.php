<?php

namespace App\Factory;

use App\Entity\UserSkill;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<UserSkill>
 */
final class UserSkillFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return UserSkill::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'owner' => lazy(fn() => UserFactory::randomOrCreate()),
            'skill' => lazy(fn() => SkillFactory::randomOrCreate()),
            'type' => self::faker()->randomElement([UserSkill::TYPE_TEACH, UserSkill::TYPE_LEARN]),
            'level' => self::faker()->randomElement([
                UserSkill::LEVEL_BEGINNER,
                UserSkill::LEVEL_INTERMEDIATE,
                UserSkill::LEVEL_ADVANCED,
                UserSkill::LEVEL_EXPERT
            ]),
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this;
    }
}

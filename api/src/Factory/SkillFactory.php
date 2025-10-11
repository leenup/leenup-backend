<?php

namespace App\Factory;

use App\Entity\Skill;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use function Zenstruck\Foundry\lazy;

/**
 * @extends PersistentObjectFactory<Skill>
 */
final class SkillFactory extends PersistentObjectFactory
{
    public function __construct()
    {
    }

    #[\Override]
    public static function class(): string
    {
        return Skill::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'title' => self::faker()->words(2, true), // Ex: "PHP Symfony"
            // Utilise lazy() pour créer ou réutiliser une catégorie aléatoire
            'category' => lazy(fn() => CategoryFactory::randomOrCreate()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Skill $skill): void {})
            ;
    }
}

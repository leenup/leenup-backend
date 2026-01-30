<?php

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    #[\Override]
    protected function defaults(): array|callable
    {
        $faker = self::faker();

        $lastLoginMutable = $faker->optional(0.8)->dateTimeBetween('-30 days', 'now');
        $lastLoginAt = $lastLoginMutable !== null
            ? \DateTimeImmutable::createFromMutable($lastLoginMutable)
            : null;

        $allLanguages = ['fr', 'en', 'es', 'de', 'it'];
        $allLearningStyles = [
            'calm_explanations',
            'straight_to_the_point',
            'concrete_examples',
            'hands_on',
            'structured',
        ];

        $uglyAnimalAvatars = [
            'https://loremflickr.com/320/320/ugly,animal',
            'https://loremflickr.com/320/320/weird,animal',
            'https://loremflickr.com/320/320/funny,animal',
            'https://loremflickr.com/320/320/strange,animal',
            'https://loremflickr.com/320/320/creepy,animal',
        ];

        return [
            'email' => $faker->unique()->email(),
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',

            // Profil de base
            'firstName' => $faker->firstName(),
            'lastName' => $faker->lastName(),

            'avatarUrl' => $faker->optional(0.8)->randomElement($uglyAnimalAvatars),

            'bio' => $faker->optional(0.6)->paragraph(2),
            'location' => $faker->optional(0.8)->city() . ', ' . $faker->country(),
            'timezone' => $faker->optional(0.9)->randomElement([
                'Europe/Paris',
                'Europe/London',
                'America/New_York',
                'America/Los_Angeles',
                'Asia/Tokyo',
                'Australia/Sydney',
            ]),
            'locale' => $faker->optional(0.9)->randomElement(['fr', 'en', 'es', 'de']),

            'birthdate' => $faker->optional(0.9)->dateTimeBetween('-50 years', '-18 years'),
            'languages' => $faker->optional(0.9)->randomElements(
                $allLanguages,
                $faker->numberBetween(1, 3)
            ),
            'exchangeFormat' => $faker->optional(0.9)->randomElement(['visio', 'chat', 'audio']),
            'learningStyles' => $faker->optional(0.9)->randomElements(
                $allLearningStyles,
                $faker->numberBetween(1, 3)
            ),
            'isMentor' => $faker->boolean(40),
            'profiles' => $faker->randomElements(['mentor', 'student'], $faker->numberBetween(1, 2)),

            'isActive' => true,
            'lastLoginAt' => $lastLoginAt,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (User $user): void {
                if ($user->getPlainPassword()) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
                    $user->setPassword($hashedPassword);
                    $user->setPlainPassword(null);
                }
            });
    }
}

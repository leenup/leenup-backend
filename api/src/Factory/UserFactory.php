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
        return [
            'email' => self::faker()->unique()->email(),
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',

            // Nouveaux champs MVP
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'avatarUrl' => self::faker()->optional(0.7)->imageUrl(200, 200, 'people'),
            'bio' => self::faker()->optional(0.6)->paragraph(2),
            'location' => self::faker()->optional(0.8)->city() . ', ' . self::faker()->country(),
            'timezone' => self::faker()->optional(0.9)->randomElement([
                'Europe/Paris',
                'Europe/London',
                'America/New_York',
                'America/Los_Angeles',
                'Asia/Tokyo',
                'Australia/Sydney',
            ]),
            'locale' => self::faker()->optional(0.9)->randomElement(['fr', 'en', 'es', 'de']),
            'isActive' => true,
            'lastLoginAt' => self::faker()->optional(0.8)->dateTimeBetween('-30 days', 'now')
                ? \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-30 days', 'now'))
                : null,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function(User $user): void {
                // Hash le plainPassword après l'instantiation
                if ($user->getPlainPassword()) {
                    $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPlainPassword());
                    $user->setPassword($hashedPassword);
                    $user->setPlainPassword(null);
                }
            })
            ;
    }
}

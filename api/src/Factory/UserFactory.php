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

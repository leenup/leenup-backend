<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class ChangePasswordTest extends ApiTestCase
{
    use Factories;

    private function getToken(string $email, string $password): string
    {
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => $email, 'password' => $password],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        return $response->toArray()['token'];
    }

    // ==================== POST /me/change-password ====================

    public function testChangePasswordSuccessfully(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'oldpass123']);
        $token = $this->getToken('test@example.com', 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testChangePasswordAndLoginWithNewPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'oldpass123']);
        $token = $this->getToken('test@example.com', 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Se connecter avec le nouveau mot de passe
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'newpass123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testChangePasswordMakesOldPasswordInvalid(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'oldpass123']);
        $token = $this->getToken('test@example.com', 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Essayer de se connecter avec l'ancien mot de passe
        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'oldpass123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordKeepsTokenValid(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'oldpass123']);
        $token = $this->getToken('test@example.com', 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Le token reste valide
        static::createClient()->request('GET', '/me', ['auth_bearer' => $token]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'test@example.com']);
    }

    // ==================== Validations ====================

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'correctpass123']);
        $token = $this->getToken('test@example.com', 'correctpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'wrongpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'currentPassword',
                'message' => 'The current password is incorrect',
            ]],
        ]);
    }

    public function testChangePasswordWithSamePassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'samepass123']);
        $token = $this->getToken('test@example.com', 'samepass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'samepass123',
                'newPassword' => 'samepass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'newPassword',
                'message' => 'The new password must be different from the current password',
            ]],
        ]);
    }

    public function testChangePasswordWithTooShortPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'oldpass123']);
        $token = $this->getToken('test@example.com', 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => '123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'newPassword']],
        ]);
    }

    public function testChangePasswordWithEmptyCurrentPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password123']);
        $token = $this->getToken('test@example.com', 'password123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => '',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'currentPassword',
                'message' => 'The current password cannot be empty',
            ]],
        ]);
    }

    public function testChangePasswordWithEmptyNewPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password123']);
        $token = $this->getToken('test@example.com', 'password123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => [
                'currentPassword' => 'password123',
                'newPassword' => '',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'newPassword',
                'message' => 'The new password cannot be empty',
            ]],
        ]);
    }

    public function testChangePasswordWithMissingCurrentPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password123']);
        $token = $this->getToken('test@example.com', 'password123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => ['newPassword' => 'newpass123'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordWithMissingNewPassword(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password123']);
        $token = $this->getToken('test@example.com', 'password123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => 'password123'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // ==================== Sécurité ====================

    public function testChangePasswordWithoutAuthentication(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordWithInvalidToken(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => 'invalid_token',
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordDoesNotAffectOtherUsers(): void
    {
        UserFactory::createOne(['email' => 'user1@example.com', 'plainPassword' => 'pass1234']);
        UserFactory::createOne(['email' => 'user2@example.com', 'plainPassword' => 'pass5678']);

        $token1 = $this->getToken('user1@example.com', 'pass1234');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token1,
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // User2 peut toujours se connecter avec son ancien mot de passe
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'pass5678'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testChangePasswordMultipleTimes(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'version1pass']);
        $token = $this->getToken('test@example.com', 'version1pass');

        // Changement 1
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => 'version1pass', 'newPassword' => 'version2pass'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Changement 2
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => 'version2pass', 'newPassword' => 'version3pass'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Se connecter avec v3
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'version3pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());

        // v1 ne fonctionne plus
        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'version1pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);

        // v2 ne fonctionne plus
        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'version2pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

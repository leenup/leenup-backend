<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Zenstruck\Foundry\Test\Factories;

class ChangePasswordTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    // ==================== POST /me/change-password ====================

    public function testChangePasswordSuccessfully(): void
    {
        // user authentifié via cookie + CSRF
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'oldpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'oldpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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

        // On ne vérifie plus la présence de "token" dans le body,
        // on vérifie juste que l'auth fonctionne (200).
        $this->assertResponseIsSuccessful();
    }

    public function testChangePasswordMakesOldPasswordInvalid(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'oldpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        // Ici on teste que la session (cookies) reste valide
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'oldpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Le même client (mêmes cookies) doit toujours pouvoir appeler /me
        $client->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'test@example.com']);
    }

    // ==================== Validations ====================

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'correctpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'samepass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'oldpass123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'password123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'password123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'password123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => ['newPassword' => 'newpass123'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordWithMissingNewPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'password123'
        );

        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
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
        // Dans le nouveau modèle, un "invalid token" = une requête sans vraie session
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
        // user1: celui qui change son mot de passe
        [$client1, $csrfToken1, $user1] = $this->createAuthenticatedUser(
            'user1@example.com',
            'pass1234'
        );

        // user2: simple user créé via factory, on le testera ensuite
        UserFactory::createOne([
            'email' => 'user2@example.com',
            'plainPassword' => 'pass5678',
        ]);

        $this->requestUnsafe($client1, 'POST', '/me/change-password', $csrfToken1, [
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
    }

    public function testChangePasswordMultipleTimes(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            'test@example.com',
            'version1pass'
        );

        // Changement 1
        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'version1pass',
                'newPassword' => 'version2pass',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Changement 2
        $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'version2pass',
                'newPassword' => 'version3pass',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Se connecter avec v3
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'version3pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

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

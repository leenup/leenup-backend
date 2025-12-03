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
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-success@example.com',
            password: 'oldpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(204, $response->getStatusCode());
    }

    public function testChangePasswordAndLoginWithNewPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-login@example.com',
            password: 'oldpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(204, $response->getStatusCode());

        // Se connecter avec le nouveau mot de passe
        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test-change-login@example.com', 'password' => 'newpass123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        // On ne vérifie plus la présence de "token" dans le body,
        // on vérifie juste que l'auth fonctionne (200).
        self::assertSame(200, $authResponse->getStatusCode());
    }

    public function testChangePasswordMakesOldPasswordInvalid(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-old-invalid@example.com',
            password: 'oldpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(204, $response->getStatusCode());

        // Essayer de se connecter avec l'ancien mot de passe
        $authClient = static::createClient();
        $authClient->request('POST', '/auth', [
            'json' => ['email' => 'test-change-old-invalid@example.com', 'password' => 'oldpass123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordKeepsTokenValid(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-keep-session@example.com',
            password: 'oldpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(204, $response->getStatusCode());

        // Le même client (mêmes cookies) doit toujours pouvoir appeler /me
        $meResponse = $client->request('GET', '/me');

        self::assertSame(200, $meResponse->getStatusCode());
        $this->assertJsonContains(['email' => 'test-change-keep-session@example.com']);
    }

    // ==================== Validations ====================

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-wrong-current@example.com',
            password: 'correctpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'wrongpass123',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
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
            email: 'test-change-same@example.com',
            password: 'samepass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'samepass123',
                'newPassword' => 'samepass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
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
            email: 'test-change-short@example.com',
            password: 'oldpass123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => '123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'newPassword']],
        ]);
    }

    public function testChangePasswordWithEmptyCurrentPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-empty-current@example.com',
            password: 'password123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => '',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
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
            email: 'test-change-empty-new@example.com',
            password: 'password123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'password123',
                'newPassword' => '',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
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
            email: 'test-change-missing-current@example.com',
            password: 'password123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => ['newPassword' => 'newpass123'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testChangePasswordWithMissingNewPassword(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-missing-new@example.com',
            password: 'password123',
        );

        $response = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => ['currentPassword' => 'password123'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    // ==================== Sécurité ====================

    public function testChangePasswordWithoutAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/me/change-password', [
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordWithInvalidToken(): void
    {
        // Dans le nouveau modèle, un "invalid token" = une requête sans vraie session
        $client = static::createClient();

        $client->request('POST', '/me/change-password', [
            'auth_bearer' => 'invalid_token',
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordDoesNotAffectOtherUsers(): void
    {
        // user1: celui qui change son mot de passe
        [$client1, $csrfToken1, $user1] = $this->createAuthenticatedUser(
            email: 'user1-change-pass@example.com',
            password: 'pass1234',
        );

        // user2: simple user créé via factory, on le testera ensuite
        UserFactory::createOne([
            'email' => 'user2-change-pass@example.com',
            'plainPassword' => 'pass5678',
        ]);

        $response = $this->requestUnsafe($client1, 'POST', '/me/change-password', $csrfToken1, [
            'json' => [
                'currentPassword' => 'pass1234',
                'newPassword' => 'newpass123',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(204, $response->getStatusCode());

        // User2 peut toujours se connecter avec son ancien mot de passe
        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2-change-pass@example.com', 'password' => 'pass5678'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertSame(200, $authResponse->getStatusCode());
    }

    public function testChangePasswordMultipleTimes(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'test-change-multiple@example.com',
            password: 'version1pass',
        );

        // Changement 1
        $response1 = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'version1pass',
                'newPassword' => 'version2pass',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        self::assertSame(204, $response1->getStatusCode());

        // Changement 2
        $response2 = $this->requestUnsafe($client, 'POST', '/me/change-password', $csrfToken, [
            'json' => [
                'currentPassword' => 'version2pass',
                'newPassword' => 'version3pass',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        self::assertSame(204, $response2->getStatusCode());

        // Se connecter avec v3
        $clientAuth = static::createClient();
        $authV3 = $clientAuth->request('POST', '/auth', [
            'json' => ['email' => 'test-change-multiple@example.com', 'password' => 'version3pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertSame(200, $authV3->getStatusCode());

        // v1 ne fonctionne plus
        $clientAuth->request('POST', '/auth', [
            'json' => ['email' => 'test-change-multiple@example.com', 'password' => 'version1pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(401);

        // v2 ne fonctionne plus
        $clientAuth->request('POST', '/auth', [
            'json' => ['email' => 'test-change-multiple@example.com', 'password' => 'version2pass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(401);
    }
}

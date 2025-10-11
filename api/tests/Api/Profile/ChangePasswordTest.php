<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class ChangePasswordTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static string $token;
    private static string $email;
    private static string $password = 'initial_password123';
    private static bool $initialized = false;
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        self::$counter++;

        if (!self::$initialized) {
            self::$email = 'change-password-test@example.com';
            self::$token = $this->createAuthenticatedUser(self::$email, self::$password);
            self::$initialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$initialized = false;
        self::$counter = 0;
    }

    // ==================== POST /me/change-password ====================

    public function testChangePasswordSuccessfully(): void
    {
        $testToken = $this->createAuthenticatedUser("success-" . self::$counter . "@example.com", 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testChangePasswordAndLoginWithNewPassword(): void
    {
        $email = "login-new-" . self::$counter . "@example.com";
        $testToken = $this->createAuthenticatedUser($email, 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testChangePasswordMakesOldPasswordInvalid(): void
    {
        $email = "invalid-old-" . self::$counter . "@example.com";
        $testToken = $this->createAuthenticatedUser($email, 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'oldpass123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordKeepsTokenValid(): void
    {
        $email = "token-valid-" . self::$counter . "@example.com";
        $testToken = $this->createAuthenticatedUser($email, 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $testToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => $email,
        ]);
    }

    // ==================== Validations ====================

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        $testToken = $this->createAuthenticatedUser("incorrect-" . self::$counter . "@example.com", 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'wrong_password',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'currentPassword',
                    'message' => 'The current password is incorrect',
                ],
            ],
        ]);
    }

    public function testChangePasswordWithSamePassword(): void
    {
        $testToken = $this->createAuthenticatedUser("same-pass-" . self::$counter . "@example.com", 'mypassword123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'mypassword123',
                'newPassword' => 'mypassword123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'newPassword',
                    'message' => 'The new password must be different from the current password',
                ],
            ],
        ]);
    }

    public function testChangePasswordWithTooShortPassword(): void
    {
        $testToken = $this->createAuthenticatedUser("short-pass-" . self::$counter . "@example.com", 'oldpass123');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $testToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'oldpass123',
                'newPassword' => '123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'newPassword',
                ],
            ],
        ]);
    }

    public function testChangePasswordWithEmptyCurrentPassword(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => self::$token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => '',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'currentPassword',
                    'message' => 'The current password cannot be empty',
                ],
            ],
        ]);
    }

    public function testChangePasswordWithEmptyNewPassword(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => self::$token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => self::$password,
                'newPassword' => '',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'newPassword',
                    'message' => 'The new password cannot be empty',
                ],
            ],
        ]);
    }

    public function testChangePasswordWithMissingCurrentPassword(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => self::$token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangePasswordWithMissingNewPassword(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => self::$token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => self::$password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // ==================== Sécurité ====================

    public function testChangePasswordWithoutAuthentication(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'somepassword',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordWithInvalidToken(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => 'invalid_token_12345',
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'somepassword',
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordDoesNotAffectOtherUsers(): void
    {
        $email1 = "user1-" . self::$counter . "@example.com";
        $email2 = "user2-" . self::$counter . "@example.com";

        $token1 = $this->createAuthenticatedUser($email1, 'password1');
        $token2 = $this->createAuthenticatedUser($email2, 'password2');

        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $token1,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'password1',
                'newPassword' => 'new_password_user1',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email2,
                'password' => 'password2',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    // ==================== Cas complexes ====================

    public function testChangePasswordMultipleTimes(): void
    {
        $email = "multi-change-" . self::$counter . "@example.com";
        $token = $this->createAuthenticatedUser($email, 'password_v1');

        // Premier changement
        $client = static::createClient();
        $client->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'password_v1',
                'newPassword' => 'password_v2',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Deuxième changement
        $client->request('POST', '/me/change-password', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => 'password_v2',
                'newPassword' => 'password_v3',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Se connecter avec le dernier mot de passe
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'password_v3',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());

        // Vérifier que password_v1 ne fonctionne plus
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'password_v1',
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);

        // Vérifier que password_v2 ne fonctionne plus
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'password_v2',
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

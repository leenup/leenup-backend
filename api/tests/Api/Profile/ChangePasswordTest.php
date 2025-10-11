<?php
// api/tests/Api/Profile/ChangePasswordTest.php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class ChangePasswordTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private string $token;
    private string $email = 'change-password-test@example.com';
    private string $password = 'initial_password123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->createAuthenticatedUser($this->email, $this->password);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    // ==================== POST /me/change-password ====================

    public function testChangePasswordSuccessfully(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testChangePasswordAndLoginWithNewPassword(): void
    {
        // Changer le mot de passe
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Se connecter avec le nouveau mot de passe
        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $this->email,
                'password' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testChangePasswordMakesOldPasswordInvalid(): void
    {
        // Changer le mot de passe
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Essayer de se connecter avec l'ancien mot de passe
        static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $this->email,
                'password' => $this->password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordKeepsTokenValid(): void
    {
        // Changer le mot de passe
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que le token actuel fonctionne toujours
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => $this->email,
        ]);
    }

    // ==================== Validations ====================

    public function testChangePasswordWithIncorrectCurrentPassword(): void
    {
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
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
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => $this->password,
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
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
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
            'auth_bearer' => $this->token,
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
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
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
            'auth_bearer' => $this->token,
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
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
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
                'currentPassword' => $this->password,
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
                'currentPassword' => $this->password,
                'newPassword' => 'new_secure_password456',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangePasswordDoesNotAffectOtherUsers(): void
    {
        // Créer un deuxième utilisateur
        $token2 = $this->createAuthenticatedUser('other-user@example.com', 'other_password123');

        // Le premier utilisateur change son mot de passe
        static::createClient()->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'new_password_user1',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que le deuxième utilisateur peut toujours se connecter avec son mot de passe
        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'other-user@example.com',
                'password' => 'other_password123',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    // ==================== Cas complexes ====================

    public function testChangePasswordMultipleTimes(): void
    {
        $client = static::createClient();

        // Premier changement
        $client->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'currentPassword' => $this->password,
                'newPassword' => 'password_v2',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Deuxième changement
        $client->request('POST', '/me/change-password', [
            'auth_bearer' => $this->token,
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
                'email' => $this->email,
                'password' => 'password_v3',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());

        // Vérifier que les anciens mots de passe ne fonctionnent plus
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $this->email,
                'password' => $this->password,
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);

        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $this->email,
                'password' => 'password_v2',
            ],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

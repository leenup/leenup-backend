<?php
// api/tests/Api/Profile/CurrentUserTest.php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class CurrentUserTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->createAuthenticatedUser('current-user-test@example.com', 'password123');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'current-user-test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);

        // Vérifier que le mot de passe n'est pas exposé
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testGetCurrentUserProfileWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCurrentUserProfileWithInvalidToken(): void
    {
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => 'invalid_token_12345',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== Vérifier que chaque utilisateur voit son propre profil ====================

    public function testDifferentUsersGetTheirOwnProfile(): void
    {
        // Créer un deuxième utilisateur
        $token2 = $this->createAuthenticatedUser('second-user@example.com', 'password456');

        // Premier utilisateur récupère son profil
        $response1 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();
        $this->assertEquals('current-user-test@example.com', $data1['email']);

        // Deuxième utilisateur récupère son profil
        $response2 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();
        $this->assertEquals('second-user@example.com', $data2['email']);

        // Vérifier que les IDs sont différents
        $this->assertNotEquals($data1['id'], $data2['id']);
    }

    // ==================== Vérifier la structure de la réponse ====================

    public function testCurrentUserProfileResponseStructure(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Vérifier la présence des champs obligatoires
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);

        // Vérifier les types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['email']);
        $this->assertIsArray($data['roles']);

        // Vérifier que @type est correct
        $this->assertEquals('User', $data['@type']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        $response = static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'updated-email@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'updated-email@example.com',
        ]);

        $client = static::createClient();
        $tokenResponse = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'updated-email@example.com',
                'password' => 'password123',
            ],
        ]);
        $newToken = $tokenResponse->toArray()['token'];

        $verifyResponse = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $newToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'updated-email@example.com',
        ]);
    }

    public function testUpdateCurrentUserEmailWithoutAuthentication(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'json' => [
                'email' => 'hacker@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateCurrentUserEmailWithInvalidEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'invalid-email-format',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailWithDuplicateEmail(): void
    {
        $this->createAuthenticatedUser('existing-user@example.com', 'password');

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'existing-user@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This email is already in use',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => '',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailMultipleTimes(): void
    {
        $client = static::createClient();

        // Première mise à jour
        $response1 = $client->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'first-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'first-update@example.com',
        ]);

        $tokenResponse1 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'first-update@example.com',
                'password' => 'password123',
            ],
        ]);
        $token1 = $tokenResponse1->toArray()['token'];

        $response2 = $client->request('PATCH', '/me', [
            'auth_bearer' => $token1,
            'json' => [
                'email' => 'second-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'second-update@example.com',
        ]);

        $tokenResponse2 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'second-update@example.com',
                'password' => 'password123',
            ],
        ]);
        $token2 = $tokenResponse2->toArray()['token'];

        $verifyResponse = $client->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'second-update@example.com',
        ]);
    }

    public function testUpdateCurrentUserDoesNotAffectOtherUsers(): void
    {
        $token2 = $this->createAuthenticatedUser('other-user@example.com', 'password');

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'updated-first-user@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $response2 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'other-user@example.com',
        ]);
    }

    public function testUpdateCurrentUserEmailDoesNotExposePassword(): void
    {
        $response = static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'secure-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }
}

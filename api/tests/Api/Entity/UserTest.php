<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class UserTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static string $userToken;
    private static string $adminToken;
    private static bool $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les users une seule fois pour toute la classe
        if (!self::$initialized) {
            self::$userToken = $this->createAuthenticatedUser('user@exemple.com', 'user123!');
            self::$adminToken = $this->createAuthenticatedAdmin('admin@exemple.com', 'admin123!');
            self::$initialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$initialized = false;
    }

    // ==================== GET /users (Collection) ====================

    public function testGetUsersAsAdmin(): void
    {
        $this->createAuthenticatedUser('user2@exemple.com', 'user123!');
        $this->createAuthenticatedUser('user3@exemple.com', 'user123!');

        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => self::$adminToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/User', $data['@context']);
        $this->assertSame('Collection', $data['@type']);
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(4, $data['totalItems']);

        $emails = array_column($data['member'], 'email');
        $this->assertContains('user@exemple.com', $emails);
        $this->assertContains('admin@exemple.com', $emails);

        // Vérifier qu'aucun mot de passe n'est exposé
        foreach ($data['member'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('plainPassword', $user);
        }
    }

    public function testGetUsersAsUserForbidden(): void
    {
        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => self::$userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/Error', $data['@context']);
        $this->assertSame('Error', $data['@type']);
        $this->assertSame(403, $data['status']);
        $this->assertSame('Only admins can list users.', $data['detail']);
    }

    public function testGetUsersWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/users');

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== GET /users/{id} (Item) ====================

    public function testGetUserAsAdmin(): void
    {
        $userData = $this->createUserAndGetId('target-user@exemple.com', 'pass123');

        $response = static::createClient()->request('GET', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'target-user@exemple.com',
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testGetUserAsUserForbidden(): void
    {
        $userData = $this->createUserAndGetId('other-user@exemple.com', 'pass123');

        static::createClient()->request('GET', "/users/{$userData['id']}", [
            'auth_bearer' => self::$userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can view user details.',
        ]);
    }

    public function testGetUserWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/users/1');

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== PATCH /users/{id} ====================

    public function testAdminCanUpdateAnyUser(): void
    {
        $userData = $this->createUserAndGetId('target@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
            'json' => [
                'email' => 'updated-by-admin@exemple.com',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'updated-by-admin@exemple.com',
        ]);
    }

    public function testAdminCanChangeUserRoles(): void
    {
        $userData = $this->createUserAndGetId('promote-me@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
            'json' => [
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);
    }

    public function testAdminCanChangeUserPassword(): void
    {
        $userData = $this->createUserAndGetId('change-pass@exemple.com', 'oldpass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
            'json' => [
                'plainPassword' => 'newpass456',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier que le nouveau mot de passe fonctionne
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'change-pass@exemple.com',
                'password' => 'newpass456',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginResponse->toArray());
    }

    public function testUserCanUpdateOwnEmail(): void
    {
        $userData = $this->createUserAndGetId('my-email@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => $userData['token'],
            'json' => [
                'email' => 'my-new-email@exemple.com',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'my-new-email@exemple.com',
        ]);
    }

    public function testUserCannotUpdateOtherUserEmail(): void
    {
        $userData = $this->createUserAndGetId('other@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$userToken,
            'json' => [
                'email' => 'hacked@exemple.com',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCannotChangeOwnRoles(): void
    {
        $userData = $this->createUserAndGetId('no-promo@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => $userData['token'],
            'json' => [
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can modify user roles.',
        ]);
    }

    public function testUpdateUserWithInvalidEmail(): void
    {
        $userData = $this->createUserAndGetId('valid@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
            'json' => [
                'email' => 'invalid-email',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'email'],
            ],
        ]);
    }

    public function testUpdateUserWithDuplicateEmail(): void
    {
        $this->createUserAndGetId('existing@exemple.com', 'pass123');
        $userData = $this->createUserAndGetId('target2@exemple.com', 'pass123');

        static::createClient()->request('PATCH', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
            'json' => [
                'email' => 'existing@exemple.com',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
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

    // ==================== DELETE /users/{id} ====================

    public function testAdminCanDeleteUser(): void
    {
        $userData = $this->createUserAndGetId('delete-me@exemple.com', 'pass123');

        static::createClient()->request('DELETE', "/users/{$userData['id']}", [
            'auth_bearer' => self::$adminToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que l'utilisateur ne peut plus se connecter
        static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'delete-me@exemple.com',
                'password' => 'pass123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUserCannotDeleteOtherUser(): void
    {
        $userData = $this->createUserAndGetId('dont-delete-me@exemple.com', 'pass123');

        static::createClient()->request('DELETE', "/users/{$userData['id']}", [
            'auth_bearer' => self::$userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can delete users.',
        ]);
    }

    public function testUserCannotDeleteThemselves(): void
    {
        $userData = $this->createUserAndGetId('suicide@exemple.com', 'pass123');

        static::createClient()->request('DELETE', "/users/{$userData['id']}", [
            'auth_bearer' => $userData['token'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can delete users.',
        ]);
    }

    public function testDeleteUserWithoutAuthentication(): void
    {
        static::createClient()->request('DELETE', '/users/1');

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== Helper Methods ====================

    /**
     * Crée un utilisateur et retourne son ID + token
     */
    private function createUserAndGetId(string $email, string $password): array
    {
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        // Supprimer si existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, $password)
        );

        $em->persist($user);
        $em->flush();

        // Obtenir le token
        $client = self::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        return [
            'id' => $user->getId(),
            'token' => $response->toArray()['token'],
        ];
    }
}

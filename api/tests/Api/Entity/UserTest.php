<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class UserTest extends ApiTestCase
{
    use Factories;

    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur normal
        UserFactory::createOne([
            'email' => 'user@exemple.com',
            'plainPassword' => 'user123',
            'roles' => ['ROLE_USER'],
        ]);

        // Créer un admin
        UserFactory::createOne([
            'email' => 'admin@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

        // Obtenir les tokens
        $client = static::createClient();

        $response = $client->request('POST', '/auth', [
            'json' => ['email' => 'user@exemple.com', 'password' => 'user123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->userToken = $response->toArray()['token'];

        $response = $client->request('POST', '/auth', [
            'json' => ['email' => 'admin@exemple.com', 'password' => 'admin123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->adminToken = $response->toArray()['token'];
    }

    // ==================== GET /users (Collection) ====================

    public function testGetUsersAsAdmin(): void
    {
        UserFactory::createMany(2); // 2 utilisateurs aléatoires supplémentaires

        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/User', $data['@context']);
        $this->assertSame('Collection', $data['@type']);
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(4, $data['totalItems']); // 2 setUp + 2 créés

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
            'auth_bearer' => $this->userToken,
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
        $user = UserFactory::createOne(['email' => 'target@exemple.com']);

        $response = static::createClient()->request('GET', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'target@exemple.com',
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testGetUserAsUserForbidden(): void
    {
        $user = UserFactory::createOne(['email' => 'other@exemple.com']);

        static::createClient()->request('GET', '/users/' . $user->getId(), [
            'auth_bearer' => $this->userToken,
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
        $user = UserFactory::createOne(['email' => 'target@exemple.com']);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['email' => 'updated@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'updated@exemple.com']);
    }

    public function testAdminCanChangeUserRoles(): void
    {
        $user = UserFactory::createOne(['email' => 'promote@exemple.com']);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['roles' => ['ROLE_ADMIN']],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);
    }

    public function testAdminCanChangeUserPassword(): void
    {
        $user = UserFactory::createOne([
            'email' => 'changepass@exemple.com',
            'plainPassword' => 'oldpass',
        ]);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['plainPassword' => 'newpass'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier que le nouveau mot de passe fonctionne
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'changepass@exemple.com', 'password' => 'newpass'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginResponse->toArray());
    }

    public function testUserCanUpdateOwnEmail(): void
    {
        $user = UserFactory::createOne([
            'email' => 'myemail@exemple.com',
            'plainPassword' => 'password',
        ]);

        // Obtenir le token de cet utilisateur
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'myemail@exemple.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $response->toArray()['token'];

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $token,
            'json' => ['email' => 'newemail@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@exemple.com']);
    }

    public function testUserCannotUpdateOtherUserEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'other@exemple.com']);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'hacked@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCannotChangeOwnRoles(): void
    {
        $user = UserFactory::createOne([
            'email' => 'nopromo@exemple.com',
            'plainPassword' => 'password',
        ]);

        // Obtenir le token de cet utilisateur
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'nopromo@exemple.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $response->toArray()['token'];

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $token,
            'json' => ['roles' => ['ROLE_ADMIN']],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can modify user roles.',
        ]);
    }

    public function testUpdateUserWithInvalidEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'valid@exemple.com']);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['email' => 'invalid-email'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'email'],
            ],
        ]);
    }

    public function testUpdateUserWithDuplicateEmail(): void
    {
        UserFactory::createOne(['email' => 'existing@exemple.com']);
        $user = UserFactory::createOne(['email' => 'target@exemple.com']);

        static::createClient()->request('PATCH', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['email' => 'existing@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
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
        $user = UserFactory::createOne([
            'email' => 'deleteme@exemple.com',
            'plainPassword' => 'password',
        ]);

        static::createClient()->request('DELETE', '/users/' . $user->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que l'utilisateur ne peut plus se connecter
        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'deleteme@exemple.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUserCannotDeleteOtherUser(): void
    {
        $user = UserFactory::createOne(['email' => 'dontdelete@exemple.com']);

        static::createClient()->request('DELETE', '/users/' . $user->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can delete users.',
        ]);
    }

    public function testUserCannotDeleteThemselves(): void
    {
        $user = UserFactory::createOne([
            'email' => 'suicide@exemple.com',
            'plainPassword' => 'password',
        ]);

        // Obtenir le token de cet utilisateur
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'suicide@exemple.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $response->toArray()['token'];

        static::createClient()->request('DELETE', '/users/' . $user->getId(), [
            'auth_bearer' => $token,
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
}

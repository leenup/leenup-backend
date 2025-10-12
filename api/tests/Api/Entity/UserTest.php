<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class UserTest extends ApiTestCase
{
    use Factories;

    private User $userTarget;
    private User $adminTarget;
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Pour les test Users
        UserFactory::createOne([
            'email' => 'user@exemple.com',
            'plainPassword' => 'user123',
            'roles' => ['ROLE_USER'],
        ]);

        // Pour les test Admins
        UserFactory::createOne([
            'email' => 'admin@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

        $this->userTarget = UserFactory::createOne([
            'email' => 'user-target@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_USER'],
        ]);

        $this->adminTarget = UserFactory::createOne([
            'email' => 'admin-target@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

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
        UserFactory::createMany(2);

        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => $this->adminToken,
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

        foreach ($data['member'] as $user) {
            $this->assertArrayNotHasKey('password', $user);
            $this->assertArrayNotHasKey('plainPassword', $user);
        }
    }

    public function testGetUsersAsUser(): void
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
        $response = static::createClient()->request('GET', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => $this->userTarget->getEmail()
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testGetUserAsUser(): void
    {
        static::createClient()->request('GET', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can view user details.',
        ]);
    }

    public function testGetUserWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/users/' . $this->userTarget->getId());

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== PATCH /users/{id} ====================

    public function testUpdateUserAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['email' => 'user-target-updated@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'user-target-updated@exemple.com']);
    }

    public function testUpdateUserRoleAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['roles' => ['ROLE_ADMIN']],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => $this->userTarget->getEmail(),
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);
    }

    public function testUpdateUserPasswordAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['plainPassword' => 'updated123'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier que le nouveau mot de passe fonctionne
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => $this->userTarget->getEmail(), 'password' => 'updated123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginResponse->toArray());
    }

    public function testUpdateAdminAsAdmin(): void
    {

        static::createClient()->request('PATCH', '/users/' . $this->adminTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['email' => 'newemail@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Admins cannot modify admin users (including themselves).',
        ]);
    }

    public function testUpdateUserAsUser(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'update-user@exemple.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only admins can update users.',
        ]);
    }

    public function testAdminUpdateUserWithInvalidEmail(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
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

        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
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

    public function testUserCannotDeleteOtherUser(): void
    {
        static::createClient()->request('DELETE', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->userToken,
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

    public function testDeleteUserAsAdmin(): void
    {
        static::createClient()->request('DELETE', '/users/' . $this->userTarget->getId(), [
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

    public function testDeleteAdminAsAdmin(): void
    {
        static::createClient()->request('DELETE', '/users/' . $this->adminTarget->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Admins cannot delete admin users (including themselves).',
        ]);
    }
}

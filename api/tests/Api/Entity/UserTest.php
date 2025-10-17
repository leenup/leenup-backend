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
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
        ]);

        $this->adminTarget = UserFactory::createOne([
            'email' => 'admin-target@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'firstName' => 'Admin',
            'lastName' => 'User',
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
            $this->assertArrayHasKey('firstName', $user);
            $this->assertArrayHasKey('lastName', $user);
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

    // ==================== Tests des Filtres et Order ====================

    public function testFilterUsersByEmail(): void
    {
        UserFactory::createOne(['email' => 'john.doe@exemple.com']);
        UserFactory::createOne(['email' => 'jane.smith@exemple.com']);

        $response = static::createClient()->request('GET', '/users?email=john', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        $this->assertContains('john.doe@exemple.com', $emails);
        $this->assertNotContains('jane.smith@exemple.com', $emails);
    }

    public function testOrderUsersByEmailAsc(): void
    {
        $response = static::createClient()->request('GET', '/users?order[email]=asc', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        $sortedEmails = $emails;
        sort($sortedEmails);

        $this->assertSame($sortedEmails, $emails);
    }

    public function testOrderUsersByEmailDesc(): void
    {
        $response = static::createClient()->request('GET', '/users?order[email]=desc', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        $sortedEmails = $emails;
        rsort($sortedEmails);

        $this->assertSame($sortedEmails, $emails);
    }

    public function testOrderUsersByCreatedAtDesc(): void
    {
        $response = static::createClient()->request('GET', '/users?order[createdAt]=desc', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $dates = array_column($data['member'], 'createdAt');

        for ($i = 0; $i < count($dates) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                strtotime($dates[$i + 1]),
                strtotime($dates[$i])
            );
        }
    }

    public function testOrderUsersByCreatedAtAsc(): void
    {
        $response = static::createClient()->request('GET', '/users?order[createdAt]=asc', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $dates = array_column($data['member'], 'createdAt');

        for ($i = 0; $i < count($dates) - 1; $i++) {
            $this->assertLessThanOrEqual(
                strtotime($dates[$i + 1]),
                strtotime($dates[$i])
            );
        }
    }

    public function testFilterUsersByCreatedAtAfter(): void
    {
        $today = new \DateTimeImmutable();

        $response = static::createClient()->request('GET', '/users?createdAt[after]=' . $today->format('Y-m-d'), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        foreach ($data['member'] as $user) {
            $createdAt = new \DateTimeImmutable($user['createdAt']);
            $this->assertGreaterThanOrEqual($today->setTime(0, 0, 0), $createdAt);
        }
    }

    public function testFilterUsersByCreatedAtBefore(): void
    {
        $tomorrow = (new \DateTimeImmutable())->modify('+1 day');

        $response = static::createClient()->request('GET', '/users?createdAt[before]=' . $tomorrow->format('Y-m-d'), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        foreach ($data['member'] as $user) {
            $createdAt = new \DateTimeImmutable($user['createdAt']);
            $this->assertLessThan($tomorrow->setTime(0, 0, 0), $createdAt);
        }
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
            'email' => $this->userTarget->getEmail(),
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
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

    public function testUpdateUserFirstNameAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['firstName' => 'UpdatedFirstName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['firstName' => 'UpdatedFirstName']);
    }

    public function testUpdateUserLastNameAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['lastName' => 'UpdatedLastName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['lastName' => 'UpdatedLastName']);
    }

    public function testUpdateUserBioAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['bio' => 'Updated bio'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['bio' => 'Updated bio']);
    }

    public function testUpdateUserLocationAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['location' => 'London, UK'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['location' => 'London, UK']);
    }

    public function testUpdateUserTimezoneAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['timezone' => 'America/New_York'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['timezone' => 'America/New_York']);
    }

    public function testUpdateUserLocaleAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['locale' => 'en'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['locale' => 'en']);
    }

    public function testUpdateUserAvatarUrlAsAdmin(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['avatarUrl' => 'https://example.com/avatar.jpg'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['avatarUrl' => 'https://example.com/avatar.jpg']);
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

    public function testUpdateUserFirstNameTooShort(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['firstName' => 'A'], // 1 caractère (min 2)
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'firstName']],
        ]);
    }

    public function testUpdateUserFirstNameTooLong(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['firstName' => str_repeat('a', 101)], // 101 caractères (max 100)
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'firstName']],
        ]);
    }

    public function testUpdateUserBioTooLong(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['bio' => str_repeat('a', 501)], // 501 caractères (max 500)
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'bio']],
        ]);
    }

    public function testUpdateUserAvatarUrlInvalid(): void
    {
        static::createClient()->request('PATCH', '/users/' . $this->userTarget->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['avatarUrl' => 'not-a-valid-url'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'avatarUrl']],
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
            'json' => ['email' => $this->userTarget->getEmail(), 'password' => 'admin123'],
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

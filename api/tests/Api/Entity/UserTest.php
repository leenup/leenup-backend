<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class UserTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private User $userTarget;
    private User $adminTarget;

    private User $authUser;
    private User $authAdmin;

    private HttpClientInterface $userClient;
    private HttpClientInterface $adminClient;

    private string $userCsrfToken;
    private string $adminCsrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        $uniqueId = uniqid('user_test_', true);

        // Cible "user" pour les tests item/patch/delete
        $this->userTarget = UserFactory::createOne([
            'email' => 'user-target@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_USER'],
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
            'birthdate' => new \DateTimeImmutable('1995-05-10'),
            'languages' => ['fr', 'en'],
            'exchangeFormat' => 'visio',
            'learningStyles' => ['calm_explanations', 'hands_on'],
            'isMentor' => false,
        ]);

        // Cible admin pour tests de sécurité
        $this->adminTarget = UserFactory::createOne([
            'email' => 'admin-target@exemple.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'firstName' => 'Admin',
            'lastName' => 'User',
        ]);

        // Auth user via le trait (CSRF + cookies)
        [
            $this->userClient,
            $this->userCsrfToken,
            $this->authUser,
        ] = $this->createAuthenticatedUser(
            email: "user-{$uniqueId}@exemple.com",
            password: 'user123',
        );

        // Auth admin via le trait (CSRF + cookies)
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->authAdmin,
        ] = $this->createAuthenticatedAdmin(
            email: "admin-{$uniqueId}@exemple.com",
            password: 'admin123',
        );
    }

    // ==================== GET /users (Collection) ====================

    public function testGetUsersAsAdmin(): void
    {
        UserFactory::createMany(2);

        $response = $this->adminClient->request('GET', '/users');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();

        self::assertArrayHasKey('@context', $data);
        self::assertSame('/contexts/User', $data['@context']);
        self::assertSame('Collection', $data['@type']);
        self::assertArrayHasKey('member', $data);
        self::assertGreaterThanOrEqual(4, $data['totalItems']);

        $emails = array_column($data['member'], 'email');
        self::assertContains($this->authUser->getEmail(), $emails);
        self::assertContains($this->authAdmin->getEmail(), $emails);

        foreach ($data['member'] as $user) {
            self::assertArrayNotHasKey('password', $user);
            self::assertArrayNotHasKey('plainPassword', $user);
            self::assertArrayHasKey('firstName', $user);
            self::assertArrayHasKey('lastName', $user);
        }
    }

    public function testGetUsersAsUser(): void
    {
        $response = $this->userClient->request('GET', '/users');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertArrayHasKey('@context', $data);
        self::assertSame('User', $data['@type']);
    }

    public function testGetUsersWithoutAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users');

        self::assertResponseStatusCodeSame(401);
    }

    // ==================== Tests des Filtres et Order ====================

    public function testFilterUsersByEmail(): void
    {
        UserFactory::createOne(['email' => 'john.doe@exemple.com']);
        UserFactory::createOne(['email' => 'jane.smith@exemple.com']);

        $response = $this->adminClient->request('GET', '/users?email=john');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        self::assertContains('john.doe@exemple.com', $emails);
        self::assertNotContains('jane.smith@exemple.com', $emails);
    }

    public function testFilterUsersByFirstName(): void
    {
        UserFactory::createOne(['firstName' => 'Alice', 'email' => 'alice@exemple.com']);
        UserFactory::createOne(['firstName' => 'Bob', 'email' => 'bob@exemple.com']);

        $response = $this->adminClient->request('GET', '/users?firstName=Ali');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        self::assertContains('alice@exemple.com', $emails);
        self::assertNotContains('bob@exemple.com', $emails);
    }

    public function testFilterUsersByIsMentor(): void
    {
        UserFactory::createOne([
            'email' => 'mentor1@exemple.com',
            'isMentor' => true,
        ]);

        UserFactory::createOne([
            'email' => 'student1@exemple.com',
            'isMentor' => false,
        ]);

        $response = $this->adminClient->request('GET', '/users?isMentor=true');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        self::assertContains('mentor1@exemple.com', $emails);
        self::assertNotContains('student1@exemple.com', $emails);
    }

    public function testFilterUsersByBirthdateBefore(): void
    {
        UserFactory::createOne([
            'email' => 'old@exemple.com',
            'birthdate' => new \DateTimeImmutable('1980-01-01'),
        ]);

        UserFactory::createOne([
            'email' => 'young@exemple.com',
            'birthdate' => new \DateTimeImmutable('2000-01-01'),
        ]);

        $response = $this->adminClient->request('GET', '/users?birthdate[before]=1990-01-01');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        self::assertContains('old@exemple.com', $emails);
        self::assertNotContains('young@exemple.com', $emails);
    }

    public function testOrderUsersByEmailAsc(): void
    {
        $response = $this->adminClient->request('GET', '/users?order[email]=asc');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        $sortedEmails = $emails;
        sort($sortedEmails);

        self::assertSame($sortedEmails, $emails);
    }

    public function testOrderUsersByEmailDesc(): void
    {
        $response = $this->adminClient->request('GET', '/users?order[email]=desc');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $emails = array_column($data['member'], 'email');
        $sortedEmails = $emails;
        rsort($sortedEmails);

        self::assertSame($sortedEmails, $emails);
    }

    public function testOrderUsersByCreatedAtDesc(): void
    {
        $response = $this->adminClient->request('GET', '/users?order[createdAt]=desc');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $dates = array_column($data['member'], 'createdAt');

        for ($i = 0; $i < count($dates) - 1; $i++) {
            self::assertGreaterThanOrEqual(
                strtotime($dates[$i + 1]),
                strtotime($dates[$i])
            );
        }
    }

    public function testOrderUsersByCreatedAtAsc(): void
    {
        $response = $this->adminClient->request('GET', '/users?order[createdAt]=asc');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        $dates = array_column($data['member'], 'createdAt');

        for ($i = 0; $i < count($dates) - 1; $i++) {
            self::assertLessThanOrEqual(
                strtotime($dates[$i + 1]),
                strtotime($dates[$i])
            );
        }
    }

    public function testFilterUsersByCreatedAtAfter(): void
    {
        $today = new \DateTimeImmutable();

        $response = $this->adminClient->request(
            'GET',
            '/users?createdAt[after]=' . $today->format('Y-m-d')
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        foreach ($data['member'] as $user) {
            $createdAt = new \DateTimeImmutable($user['createdAt']);
            self::assertGreaterThanOrEqual($today->setTime(0, 0, 0), $createdAt);
        }
    }

    public function testFilterUsersByCreatedAtBefore(): void
    {
        $tomorrow = (new \DateTimeImmutable())->modify('+1 day');

        $response = $this->adminClient->request(
            'GET',
            '/users?createdAt[before]=' . $tomorrow->format('Y-m-d')
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        foreach ($data['member'] as $user) {
            $createdAt = new \DateTimeImmutable($user['createdAt']);
            self::assertLessThan($tomorrow->setTime(0, 0, 0), $createdAt);
        }
    }

    // ==================== GET /users/{id} (Item) ====================

    public function testGetUserAsAdmin(): void
    {
        $response = $this->adminClient->request('GET', '/users/' . $this->userTarget->getId());

        self::assertSame(200, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => $this->userTarget->getEmail(),
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
        ]);

        $data = $response->toArray();
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('plainPassword', $data);

        self::assertArrayHasKey('isMentor', $data);
        self::assertArrayHasKey('languages', $data);
        self::assertArrayHasKey('exchangeFormat', $data);
        self::assertArrayHasKey('learningStyles', $data);
        self::assertArrayHasKey('birthdate', $data);
    }

    public function testGetUserAsUser(): void
    {
        $response = $this->userClient->request('GET', '/users/' . $this->userTarget->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
    }

    public function testGetUserWithoutAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/users/' . $this->userTarget->getId());

        self::assertResponseStatusCodeSame(401);
    }

    // ==================== PATCH /users/{id} ====================

    public function testUpdateUserAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['email' => 'user-target-updated@exemple.com'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
        $this->assertJsonContains(['email' => 'user-target-updated@exemple.com']);
    }

    public function testUpdateUserFirstNameAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['firstName' => 'UpdatedFirstName'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserLastNameAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['lastName' => 'UpdatedLastName'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserBioAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['bio' => 'Updated bio'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserLocationAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['location' => 'London, UK'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserTimezoneAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['timezone' => 'America/New_York'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserLocaleAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['locale' => 'en'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserAvatarUrlAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['avatarUrl' => 'https://example.com/avatar.jpg'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUpdateUserRoleAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['roles' => ['ROLE_ADMIN']],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());
        $this->assertJsonContains([
            'email' => $this->userTarget->getEmail(),
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);
    }

    public function testUpdateUserPasswordAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['plainPassword' => 'updated123'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(200, $response->getStatusCode());

        $client = static::createClient();

        // ancien mot de passe KO
        $client->request('POST', '/auth', [
            'json' => [
                'email' => $this->userTarget->getEmail(),
                'password' => 'admin123',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(401);

        // nouveau mot de passe OK
        $client->request('POST', '/auth', [
            'json' => [
                'email' => $this->userTarget->getEmail(),
                'password' => 'updated123',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    public function testUpdateAdminAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->adminTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['email' => 'newemail@exemple.com'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(403, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Admins cannot modify admin users (including themselves).',
        ]);
    }

    public function testUpdateUserAsUser(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->userCsrfToken,
            [
                'json' => ['email' => 'update-user@exemple.com'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        if (isset($data['detail'])) {
            self::assertSame('Only admins can update users.', $data['detail']);
        }
    }

    public function testAdminUpdateUserWithInvalidEmail(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['email' => 'invalid-email'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
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

        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['email' => 'existing@exemple.com'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
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
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['firstName' => 'A'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'firstName']],
        ]);
    }

    public function testUpdateUserFirstNameTooLong(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['firstName' => str_repeat('a', 101)],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'firstName']],
        ]);
    }

    public function testUpdateUserBioTooLong(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['bio' => str_repeat('a', 501)],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'bio']],
        ]);
    }

    public function testUpdateUserAvatarUrlInvalid(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['avatarUrl' => 'not-a-valid-url'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'avatarUrl']],
        ]);
    }

    // ==================== DELETE /users/{id} ====================

    public function testUserCannotDeleteOtherUser(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/users/' . $this->userTarget->getId(),
            $this->userCsrfToken
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        if (isset($data['detail'])) {
            self::assertSame('Only admins can delete users.', $data['detail']);
        }
    }

    public function testDeleteUserWithoutAuthentication(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/users/' . $this->userTarget->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteUserAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'DELETE',
            '/users/' . $this->userTarget->getId(),
            $this->adminCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $client = static::createClient();
        $client->request('POST', '/auth', [
            'json' => ['email' => $this->userTarget->getEmail(), 'password' => 'admin123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeleteAdminAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'DELETE',
            '/users/' . $this->adminTarget->getId(),
            $this->adminCsrfToken
        );

        self::assertSame(403, $response->getStatusCode());
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Admins cannot delete admin users (including themselves).',
        ]);
    }
}

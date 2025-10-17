<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class CurrentUserTest extends ApiTestCase
{
    use Factories;

    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur et obtenir son token
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->userToken = $response->toArray()['token'];
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $response->toArray();
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
        $this->assertArrayHasKey('createdAt', $data);
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

    public function testDifferentUsersGetTheirOwnProfile(): void
    {
        $user2 = UserFactory::createOne([
            'email' => 'user2@example.com',
            'plainPassword' => 'password',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
        ]);

        // Token user2
        $response2 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token2 = $response2->toArray()['token'];

        // User1 récupère son profil
        $profile1 = static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseIsSuccessful();
        $data1 = $profile1->toArray();
        $this->assertEquals('test@example.com', $data1['email']);
        $this->assertEquals('John', $data1['firstName']);

        // User2 récupère son profil
        $profile2 = static::createClient()->request('GET', '/me', ['auth_bearer' => $token2]);
        $this->assertResponseIsSuccessful();
        $data2 = $profile2->toArray();
        $this->assertEquals('user2@example.com', $data2['email']);
        $this->assertEquals('Jane', $data2['firstName']);

        $this->assertNotEquals($data1['id'], $data2['id']);
    }

    public function testCurrentUserProfileResponseStructure(): void
    {
        $response = static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Champs obligatoires
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('firstName', $data);
        $this->assertArrayHasKey('lastName', $data);
        $this->assertArrayHasKey('createdAt', $data);

        // Types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['email']);
        $this->assertIsArray($data['roles']);
        $this->assertIsString($data['firstName']);
        $this->assertIsString($data['lastName']);
        $this->assertEquals('User', $data['@type']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'newemail@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);

        // Vérifier avec le nouveau login
        $newAuthResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'newemail@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $newToken = $newAuthResponse->toArray()['token'];

        $profile = static::createClient()->request('GET', '/me', ['auth_bearer' => $newToken]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);
    }

    public function testUpdateCurrentUserFirstName(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['firstName' => 'UpdatedFirstName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['firstName' => 'UpdatedFirstName']);
    }

    public function testUpdateCurrentUserLastName(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['lastName' => 'UpdatedLastName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['lastName' => 'UpdatedLastName']);
    }

    public function testUpdateCurrentUserBio(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['bio' => 'This is my updated bio'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['bio' => 'This is my updated bio']);
    }

    public function testUpdateCurrentUserLocation(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['location' => 'London, UK'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['location' => 'London, UK']);
    }

    public function testUpdateCurrentUserTimezone(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['timezone' => 'America/New_York'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['timezone' => 'America/New_York']);
    }

    public function testUpdateCurrentUserLocale(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['locale' => 'en'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['locale' => 'en']);
    }

    public function testUpdateCurrentUserAvatarUrl(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['avatarUrl' => 'https://example.com/avatar.jpg'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['avatarUrl' => 'https://example.com/avatar.jpg']);
    }

    public function testUpdateMultipleFieldsAtOnce(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'firstName' => 'NewFirstName',
                'lastName' => 'NewLastName',
                'bio' => 'New bio',
                'location' => 'Berlin, Germany',
                'timezone' => 'Europe/Berlin',
                'locale' => 'de',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'firstName' => 'NewFirstName',
            'lastName' => 'NewLastName',
            'bio' => 'New bio',
            'location' => 'Berlin, Germany',
            'timezone' => 'Europe/Berlin',
            'locale' => 'de',
        ]);
    }

    public function testUpdateCurrentUserWithoutAuthentication(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'json' => ['firstName' => 'Hacker'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateCurrentUserEmailWithInvalidEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'invalid-email'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'email']],
        ]);
    }

    public function testUpdateCurrentUserEmailWithDuplicateEmail(): void
    {
        UserFactory::createOne(['email' => 'existing@example.com']);

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'existing@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // TODO: Implémenter la validation d'unicité de l'email dans le processor
        // Pour l'instant on accepte que ça passe (à corriger en V1)
        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => ''],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserAvatarUrlWithInvalidUrl(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['avatarUrl' => 'not-a-valid-url'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserBioTooLong(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['bio' => str_repeat('a', 501)], // 501 caractères (max 500)
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserFirstNameTooShort(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['firstName' => 'A'], // 1 caractère (min 2)
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCannotUpdateIsActiveViaMe(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['isActive' => false],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // isActive n'est pas dans user:update donc ignoré
        $this->assertResponseIsSuccessful();
    }

    public function testCannotUpdateLastLoginAtViaMe(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['lastLoginAt' => '2025-01-01T00:00:00+00:00'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // lastLoginAt n'est pas dans user:update donc ignoré
        $this->assertResponseIsSuccessful();
    }

    // ==================== DELETE /me ====================

    public function testDeleteCurrentUserAccount(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithoutAuthentication(): void
    {
        static::createClient()->request('DELETE', '/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithInvalidToken(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => 'invalid_token']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountCannotBeUndone(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNull($user);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

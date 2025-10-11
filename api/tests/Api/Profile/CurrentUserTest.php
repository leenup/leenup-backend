<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CurrentUserTest extends ApiTestCase
{
    use ResetDatabase, Factories;

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $response->toArray()['token'];

        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $response->toArray();
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

    public function testDifferentUsersGetTheirOwnProfile(): void
    {
        $user1 = UserFactory::createOne(['email' => 'user1@example.com', 'plainPassword' => 'password']);
        $user2 = UserFactory::createOne(['email' => 'user2@example.com', 'plainPassword' => 'password']);

        // Token user1
        $response1 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user1@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token1 = $response1->toArray()['token'];

        // Token user2
        $response2 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token2 = $response2->toArray()['token'];

        // User1 récupère son profil
        $profile1 = static::createClient()->request('GET', '/me', ['auth_bearer' => $token1]);
        $this->assertResponseIsSuccessful();
        $data1 = $profile1->toArray();
        $this->assertEquals('user1@example.com', $data1['email']);

        // User2 récupère son profil
        $profile2 = static::createClient()->request('GET', '/me', ['auth_bearer' => $token2]);
        $this->assertResponseIsSuccessful();
        $data2 = $profile2->toArray();
        $this->assertEquals('user2@example.com', $data2['email']);

        $this->assertNotEquals($data1['id'], $data2['id']);
    }

    public function testCurrentUserProfileResponseStructure(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        $response = static::createClient()->request('GET', '/me', ['auth_bearer' => $token]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);

        $this->assertIsInt($data['id']);
        $this->assertIsString($data['email']);
        $this->assertIsArray($data['roles']);
        $this->assertEquals('User', $data['@type']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        UserFactory::createOne(['email' => 'old@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'old@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $token,
            'json' => ['email' => 'new@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'new@example.com']);

        // Vérifier avec le nouveau login
        $newAuthResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'new@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $newToken = $newAuthResponse->toArray()['token'];

        $profile = static::createClient()->request('GET', '/me', ['auth_bearer' => $newToken]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'new@example.com']);
    }

    public function testUpdateCurrentUserEmailWithoutAuthentication(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'json' => ['email' => 'hacker@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateCurrentUserEmailWithInvalidEmail(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $token,
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
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $token,
            'json' => ['email' => 'existing@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'email',
                'message' => 'This email is already in use',
            ]],
        ]);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        UserFactory::createOne(['email' => 'test@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $token,
            'json' => ['email' => ''],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'email']],
        ]);
    }

    // ==================== DELETE /me ====================

    public function testDeleteCurrentUserAccount(): void
    {
        UserFactory::createOne(['email' => 'delete@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $token]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        UserFactory::createOne(['email' => 'delete@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/me', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        UserFactory::createOne(['email' => 'delete@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
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
        UserFactory::createOne(['email' => 'delete@example.com', 'plainPassword' => 'password']);

        $authResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token = $authResponse->toArray()['token'];

        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $token]);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'delete@example.com']);
        $this->assertNull($user);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'delete@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }
}

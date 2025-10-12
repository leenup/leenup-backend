<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class RefreshTokenTest extends ApiTestCase
{
    use Factories;

    public function testLoginReturnsRefreshToken(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Vérifie que la réponse contient token ET refresh_token
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertIsString($data['token']);
        $this->assertIsString($data['refresh_token']);
        $this->assertNotEmpty($data['token']);
        $this->assertNotEmpty($data['refresh_token']);
    }

    public function testRefreshToken(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        // 1. Se connecter pour obtenir les tokens
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $loginData = $loginResponse->toArray();
        $refreshToken = $loginData['refresh_token'];

        // 2. Utiliser le refresh token pour obtenir de nouveaux tokens
        $refreshResponse = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => [
                'refresh_token' => $refreshToken,
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $refreshData = $refreshResponse->toArray();

        // Vérifie que la réponse contient les nouveaux tokens
        $this->assertArrayHasKey('token', $refreshData);
        $this->assertArrayHasKey('refresh_token', $refreshData);

        // Le refresh_token doit être différent (rotation activée avec single_use: true)
        $this->assertNotEquals($refreshToken, $refreshData['refresh_token']);

        // Le JWT peut être identique si généré dans la même seconde (normal)
        // On vérifie juste qu'il est présent et valide
        $this->assertNotEmpty($refreshData['token']);
    }

    public function testRefreshTokenWithInvalidToken(): void
    {
        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => [
                'refresh_token' => 'invalid_token_12345',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithoutToken(): void
    {
        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => [],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        // Le bundle renvoie 401 (Unauthorized) au lieu de 400 (Bad Request)
        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithMissingParameter(): void
    {
        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => [
                'wrong_parameter' => 'some_value',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        // Le bundle renvoie 401 (Unauthorized) au lieu de 400 (Bad Request)
        $this->assertResponseStatusCodeSame(401);
    }

    public function testNewTokenWorksForProtectedRoutes(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        // 1. Se connecter
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $refreshToken = $loginResponse->toArray()['refresh_token'];

        // 2. Obtenir un nouveau token via refresh
        $refreshResponse = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $refreshToken],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $newToken = $refreshResponse->toArray()['token'];

        // 3. Utiliser le nouveau token sur une route protégée
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $newToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'test@example.com']);
    }

    public function testOldTokenStillWorksAfterRefresh(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        // 1. Se connecter
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $loginData = $loginResponse->toArray();
        $oldToken = $loginData['token'];
        $refreshToken = $loginData['refresh_token'];

        // 2. Refresh pour obtenir un nouveau token
        static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $refreshToken],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        // 3. L'ancien JWT doit toujours fonctionner (pas expiré)
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $oldToken,
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testRefreshTokenCanBeUsedMultipleTimes(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        // 1. Se connecter
        $loginResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $refreshToken = $loginResponse->toArray()['refresh_token'];

        // 2. Premier refresh
        $refresh1 = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $refreshToken],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken2 = $refresh1->toArray()['refresh_token'];

        // 3. Deuxième refresh avec le nouveau refresh_token
        $refresh2 = static::createClient()->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => $refreshToken2],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        // 4. Vérifier que les tokens changent à chaque fois
        $this->assertNotEquals($refreshToken, $refreshToken2);
        $this->assertNotEquals($refreshToken2, $refresh2->toArray()['refresh_token']);
    }

    public function testDifferentUsersHaveDifferentRefreshTokens(): void
    {
        UserFactory::createOne(['email' => 'user1@example.com', 'plainPassword' => 'password']);
        UserFactory::createOne(['email' => 'user2@example.com', 'plainPassword' => 'password']);

        // User 1 login
        $response1 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user1@example.com', 'password' => 'password'],
        ]);
        $refreshToken1 = $response1->toArray()['refresh_token'];

        // User 2 login
        $response2 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'password'],
        ]);
        $refreshToken2 = $response2->toArray()['refresh_token'];

        // Les refresh tokens doivent être différents
        $this->assertNotEquals($refreshToken1, $refreshToken2);
    }
}

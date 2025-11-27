<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;

class RefreshTokenTest extends ApiTestCase
{
    use Factories;

    private function extractRefreshTokenFromResponse(ResponseInterface $response): ?string
    {
        $headers = $response->getHeaders(false);

        if (!isset($headers['set-cookie'])) {
            return null;
        }

        foreach ($headers['set-cookie'] as $cookieLine) {
            if (!str_contains($cookieLine, 'refresh_token=')) {
                continue;
            }

            // Exemple : "refresh_token=XXXX; Expires=...; Path=/; HttpOnly; ..."
            $parts = explode(';', $cookieLine);
            $first = $parts[0] ?? '';
            if (str_starts_with($first, 'refresh_token=')) {
                return substr($first, \strlen('refresh_token='));
            }
        }

        return null;
    }

    public function testLoginReturnsRefreshTokenCookie(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Le body contient toujours le JWT
        $this->assertArrayHasKey('token', $data);
        $this->assertIsString($data['token']);
        $this->assertNotEmpty($data['token']);

        // Le refresh_token ne doit plus être dans le body
        $this->assertArrayNotHasKey('refresh_token', $data);

        // Mais il doit être présent dans un cookie HttpOnly
        $refreshToken = $this->extractRefreshTokenFromResponse($response);
        $this->assertNotNull($refreshToken);
        $this->assertNotEmpty($refreshToken);
    }

    public function testRefreshToken(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $oldRefreshToken = $this->extractRefreshTokenFromResponse($loginResponse);
        $this->assertNotNull($oldRefreshToken);

        // 2. Refresh SANS body -> le cookie est utilisé automatiquement
        $refreshResponse = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $refreshData = $refreshResponse->toArray();

        // Le body contient le nouveau JWT
        $this->assertArrayHasKey('token', $refreshData);
        $this->assertNotEmpty($refreshData['token']);

        // Nouveau refresh token dans le cookie (rotation)
        $newRefreshToken = $this->extractRefreshTokenFromResponse($refreshResponse);
        $this->assertNotNull($newRefreshToken);
        $this->assertNotEquals($oldRefreshToken, $newRefreshToken);
    }

    public function testRefreshTokenWithInvalidTokenCookie(): void
    {
        $client = static::createClient();

        // On envoie un cookie refresh_token invalide à la main
        $client->request('POST', '/api/token/refresh', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Cookie' => 'refresh_token=invalid_token_12345',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithoutCookie(): void
    {
        $client = static::createClient();

        // Aucun cookie -> pas de refresh possible
        $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenBodyIsIgnoredWithoutCookie(): void
    {
        $client = static::createClient();

        // On envoie un body mais pas de cookie -> doit échouer aussi
        $client->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => 'some_value'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testNewTokenWorksForProtectedRoutes(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        // 2. Refresh -> nouveau JWT
        $refreshResponse = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $newToken = $refreshResponse->toArray()['token'];

        // 3. Utiliser le nouveau token sur une route protégée
        $client->request('GET', '/me', [
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

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $loginData = $loginResponse->toArray();
        $oldToken = $loginData['token'];

        // 2. Refresh (nouveau refresh token + nouveau access token)
        $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        // 3. L'ancien JWT (encore valide niveau TTL) doit toujours fonctionner
        $client->request('GET', '/me', [
            'auth_bearer' => $oldToken,
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testRefreshTokenCanBeUsedMultipleTimesWithRotation(): void
    {
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login -> premier refresh_token en cookie
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken1 = $this->extractRefreshTokenFromResponse($loginResponse);
        $this->assertNotNull($refreshToken1);

        // 2. Premier refresh
        $refreshResponse1 = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken2 = $this->extractRefreshTokenFromResponse($refreshResponse1);
        $this->assertNotNull($refreshToken2);

        // 3. Deuxième refresh (doit utiliser le 2e cookie, pas le 1er)
        $refreshResponse2 = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken3 = $this->extractRefreshTokenFromResponse($refreshResponse2);
        $this->assertNotNull($refreshToken3);

        // 4. Vérifie que les refresh tokens changent à chaque fois (rotation single_use)
        $this->assertNotEquals($refreshToken1, $refreshToken2);
        $this->assertNotEquals($refreshToken2, $refreshToken3);
    }

    public function testDifferentUsersHaveDifferentRefreshTokens(): void
    {
        UserFactory::createOne(['email' => 'user1@example.com', 'plainPassword' => 'password']);
        UserFactory::createOne(['email' => 'user2@example.com', 'plainPassword' => 'password']);

        // Client 1 : user1
        $client1 = static::createClient();
        $response1 = $client1->request('POST', '/auth', [
            'json' => ['email' => 'user1@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken1 = $this->extractRefreshTokenFromResponse($response1);
        $this->assertNotNull($refreshToken1);

        // Client 2 : user2
        $client2 = static::createClient();
        $response2 = $client2->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken2 = $this->extractRefreshTokenFromResponse($response2);
        $this->assertNotNull($refreshToken2);

        // Les refresh tokens doivent être différents
        $this->assertNotEquals($refreshToken1, $refreshToken2);
    }
}

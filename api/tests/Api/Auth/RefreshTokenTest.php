<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;

class RefreshTokenTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

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
        $email = $this->uniqueEmail('user');

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();
        $response = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();
        $data = $response->toArray(false);

        // 👉 Le body NE doit PLUS contenir le JWT (il est passé en cookie access_token)
        self::assertArrayNotHasKey('token', $data);

        // Le refresh_token ne doit plus être dans le body
        self::assertArrayNotHasKey('refresh_token', $data);

        // Mais il doit être présent dans un cookie HttpOnly
        $refreshToken = $this->extractRefreshTokenFromResponse($response);
        self::assertNotNull($refreshToken);
        self::assertNotEmpty($refreshToken);
    }

    public function testRefreshToken(): void
    {
        $email = $this->uniqueEmail('user');

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();
        $oldRefreshToken = $this->extractRefreshTokenFromResponse($loginResponse);
        self::assertNotNull($oldRefreshToken);

        // 2. Refresh SANS body -> le cookie refresh_token est utilisé automatiquement
        $refreshResponse = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseIsSuccessful();
        $refreshData = $refreshResponse->toArray(false);

        // 👉 Le body ne contient plus le nouveau JWT, il est en cookie access_token
        self::assertArrayNotHasKey('token', $refreshData);

        // Nouveau refresh token dans le cookie (rotation)
        $newRefreshToken = $this->extractRefreshTokenFromResponse($refreshResponse);
        self::assertNotNull($newRefreshToken);
        self::assertNotEquals($oldRefreshToken, $newRefreshToken);
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

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenWithoutCookie(): void
    {
        $client = static::createClient();

        // Aucun cookie -> pas de refresh possible
        $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRefreshTokenBodyIsIgnoredWithoutCookie(): void
    {
        $client = static::createClient();

        // On envoie un body mais pas de cookie -> doit échouer aussi
        $client->request('POST', '/api/token/refresh', [
            'json' => ['refresh_token' => 'some_value'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testNewTokenWorksForProtectedRoutes(): void
    {
        $email = $this->uniqueEmail('user');

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => $email, 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 2. Refresh -> nouveau JWT en cookie access_token
        $refreshResponse = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 3. Utiliser le nouveau token (cookie access_token) sur une route protégée
        //    -> on réutilise simplement le même client (cookies conservés)
        $client->request('GET', '/me');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => $email]);
    }

    public function testOldTokenStillWorksAfterRefresh(): void
    {
        $email = $this->uniqueEmail('user');

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => $email, 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 2. Refresh (nouveau refresh token + nouveau access token en cookie)
        $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 3. L'utilisateur doit toujours pouvoir appeler /me avec le même client
        $client->request('GET', '/me');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['email' => $email]);
    }

    public function testRefreshTokenCanBeUsedMultipleTimesWithRotation(): void
    {
        $email = $this->uniqueEmail('user');

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'password',
        ]);

        $client = static::createClient();

        // 1. Login -> premier refresh_token en cookie
        $loginResponse = $client->request('POST', '/auth', [
            'json' => ['email' => $email, 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $refreshToken1 = $this->extractRefreshTokenFromResponse($loginResponse);
        self::assertNotNull($refreshToken1);

        // 2. Premier refresh
        $refreshResponse1 = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $refreshToken2 = $this->extractRefreshTokenFromResponse($refreshResponse1);
        self::assertNotNull($refreshToken2);

        // 3. Deuxième refresh (doit utiliser le 2e cookie, pas le 1er)
        $refreshResponse2 = $client->request('POST', '/api/token/refresh', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $refreshToken3 = $this->extractRefreshTokenFromResponse($refreshResponse2);
        self::assertNotNull($refreshToken3);

        // 4. Vérifie que les refresh tokens changent à chaque fois (rotation single_use)
        self::assertNotEquals($refreshToken1, $refreshToken2);
        self::assertNotEquals($refreshToken2, $refreshToken3);
    }

    public function testDifferentUsersHaveDifferentRefreshTokens(): void
    {
        $email1 = $this->uniqueEmail('user1');
        $email2 = $this->uniqueEmail('user2');

        UserFactory::createOne(['email' => $email1, 'plainPassword' => 'password']);
        UserFactory::createOne(['email' => $email2, 'plainPassword' => 'password']);

        // Client 1 : user1
        $client1 = static::createClient();
        $response1 = $client1->request('POST', '/auth', [
            'json' => ['email' => $email1, 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $refreshToken1 = $this->extractRefreshTokenFromResponse($response1);
        self::assertNotNull($refreshToken1);

        // Client 2 : user2
        $client2 = static::createClient();
        $response2 = $client2->request('POST', '/auth', [
            'json' => ['email' => $email2, 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();
        $refreshToken2 = $this->extractRefreshTokenFromResponse($response2);
        self::assertNotNull($refreshToken2);

        // Les refresh tokens doivent être différents
        self::assertNotEquals($refreshToken1, $refreshToken2);
    }
}

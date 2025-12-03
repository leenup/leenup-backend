<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class AuthenticationTest extends ApiTestCase
{
    use Factories;

    public function testLogin(): void
    {
        // Créer un utilisateur avec Foundry
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => '$3CR3T',
        ]);

        // Authentification
        $client = self::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'test@example.com',
                'password' => '$3CR3T',
            ],
        ]);

        self::assertResponseIsSuccessful();

        // Le corps JSON ne doit plus contenir de champ "token"
        $json = $response->toArray(false);
        self::assertArrayNotHasKey('token', $json);

        // Vérifier la présence des cookies
        $headers = $response->getHeaders(false);

        self::assertArrayHasKey('set-cookie', $headers, 'Expected Set-Cookie headers after /auth.');
        $setCookies = $headers['set-cookie'];

        $hasAccessTokenCookie = false;
        $hasXsrfCookie = false;

        foreach ($setCookies as $cookieHeader) {
            if (str_starts_with($cookieHeader, 'access_token=')) {
                $hasAccessTokenCookie = true;
            }

            if (str_starts_with($cookieHeader, 'XSRF-TOKEN=')) {
                $hasXsrfCookie = true;
            }
        }

        self::assertTrue($hasAccessTokenCookie, 'Expected access_token cookie to be set after /auth.');
        self::assertTrue($hasXsrfCookie, 'Expected XSRF-TOKEN cookie to be set after /auth.');

        // Header X-CSRF-TOKEN présent et non vide
        $csrfHeader = $headers['x-csrf-token'][0] ?? null;
        self::assertNotNull($csrfHeader, 'Expected X-CSRF-TOKEN header after /auth.');
        self::assertNotSame('', $csrfHeader, 'X-CSRF-TOKEN header should not be empty.');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        UserFactory::createOne([
            'email' => 'user@example.com',
            'plainPassword' => 'correct-password',
        ]);

        $client = self::createClient();
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $client = self::createClient();
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'nonexistent@example.com',
                'password' => 'password',
            ],
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}

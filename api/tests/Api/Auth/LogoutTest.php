<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RefreshToken;
use App\Factory\UserFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Zenstruck\Foundry\Test\Factories;

class LogoutTest extends ApiTestCase
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

    public function testLogoutSuccessfully(): void
    {
        // 1) Création d'un utilisateur
        $email = 'logout-'.uniqid().'@example.com';

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'PASSWORD',
        ]);

        $client = static::createClient();

        // 2) Login pour récupérer les cookies + header CSRF
        $loginResponse = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'PASSWORD',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $headers = $loginResponse->getHeaders(false);

        // Header X-CSRF-TOKEN présent et non vide
        $csrfHeader = $headers['x-csrf-token'][0] ?? null;
        self::assertNotNull($csrfHeader, 'Expected X-CSRF-TOKEN header after login.');
        self::assertNotSame('', $csrfHeader);

        // Vérifier les cookies posés au login
        $setCookies = $headers['set-cookie'] ?? [];

        $hasAccessToken = false;
        $hasXsrfCookie = false;
        $hasRefreshTokenCookie = false;

        foreach ($setCookies as $cookieHeader) {
            if (str_starts_with($cookieHeader, 'access_token=')) {
                $hasAccessToken = true;
            }

            if (str_starts_with($cookieHeader, 'XSRF-TOKEN=')) {
                $hasXsrfCookie = true;
            }

            if (str_starts_with($cookieHeader, 'refresh_token=')) {
                $hasRefreshTokenCookie = true;
            }
        }

        self::assertTrue($hasAccessToken, 'Expected access_token cookie after login.');
        self::assertTrue($hasXsrfCookie, 'Expected XSRF-TOKEN cookie after login.');
        self::assertTrue($hasRefreshTokenCookie, 'Expected refresh_token cookie after login.');

        // Récupérer la valeur brute du refresh_token pour vérif BDD
        $refreshTokenString = $this->extractRefreshTokenFromResponse($loginResponse);
        self::assertNotNull($refreshTokenString, 'Expected refresh_token value in cookie after login.');
        self::assertNotSame('', $refreshTokenString);

        // 3) Vérifier qu'un refresh token correspondant existe bien en BDD avant le logout
        $refreshTokenRepository = static::getContainer()
            ->get('doctrine')
            ->getRepository(RefreshToken::class);

        $refreshTokenEntity = $refreshTokenRepository->findOneBy([
            'refreshToken' => $refreshTokenString,
        ]);

        self::assertNotNull($refreshTokenEntity, 'Refresh token should exist in database before logout.');

        // 4) Appel du logout avec le header CSRF
        $logoutResponse = $client->request('POST', '/auth/logout', [
            'headers' => [
                'X-CSRF-TOKEN' => $csrfHeader,
            ],
        ]);

        self::assertResponseStatusCodeSame(204);

        $logoutHeaders = $logoutResponse->getHeaders(false);
        $logoutCookies = $logoutHeaders['set-cookie'] ?? [];

        $accessRemoved = false;
        $csrfRemoved = false;
        $refreshRemoved = false;

        foreach ($logoutCookies as $cookieHeader) {
            if (str_starts_with($cookieHeader, 'access_token=')
                && str_contains($cookieHeader, 'expires=')
            ) {
                $accessRemoved = true;
            }

            if (str_starts_with($cookieHeader, 'XSRF-TOKEN=')
                && str_contains($cookieHeader, 'expires=')
            ) {
                $csrfRemoved = true;
            }

            if (str_starts_with($cookieHeader, 'refresh_token=')
                && str_contains($cookieHeader, 'expires=')
            ) {
                $refreshRemoved = true;
            }
        }

        self::assertTrue($accessRemoved, 'Expected access_token cookie to be removed after logout.');
        self::assertTrue($csrfRemoved, 'Expected XSRF-TOKEN cookie to be removed after logout.');
        self::assertTrue($refreshRemoved, 'Expected refresh_token cookie to be removed after logout.');

        // 5) Vérifier que le refresh token a bien été supprimé en BDD
        $remainingRefreshToken = $refreshTokenRepository->findOneBy([
            'refreshToken' => $refreshTokenString,
        ]);

        self::assertNull(
            $remainingRefreshToken,
            'Refresh token should be deleted from database after logout.'
        );
    }

    public function testLogoutWithoutCsrfShouldReturn403(): void
    {
        // 1) Création d'un utilisateur et login pour avoir les cookies (dont access_token)
        $email = 'logout-no-csrf-'.uniqid().'@example.com';

        UserFactory::createOne([
            'email' => $email,
            'plainPassword' => 'PASSWORD',
        ]);

        $client = static::createClient();

        $loginResponse = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => 'PASSWORD',
            ],
        ]);

        self::assertResponseIsSuccessful();

        // 2) Appel de /auth/logout SANS header X-CSRF-TOKEN
        $client->request('POST', '/auth/logout');

        // Le CsrfCookieRequestListener doit refuser la requête
        self::assertResponseStatusCodeSame(403);
    }

    public function testLogoutWithoutAccessTokenShouldReturn204(): void
    {
        // Ici on ne fait PAS de login, donc pas de cookie access_token
        $client = static::createClient();

        $response = $client->request('POST', '/auth/logout');

        // Le listener CSRF ne s'applique pas (pas de cookie access_token),
        // donc le controller est appelé et renvoie 204.
        self::assertResponseStatusCodeSame(204);
    }
}

<?php

namespace App\Tests\Api\Trait;

use App\Factory\UserFactory;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait AuthenticatedApiTestTrait
{
    /**
     * Génère un email unique pour éviter les collisions entre tests.
     *
     * Exemple : user_App_Tests_Api_Entity_ConversationTest_654f3c1a@test.com
     */
    protected function uniqueEmail(string $prefix = 'user'): string
    {
        return sprintf(
            '%s_%s_%s@test.com',
            $prefix,
            str_replace('\\', '_', static::class),
            uniqid()
        );
    }

    /**
     * Crée un utilisateur, se loggue via /auth (cookies + CSRF)
     * et renvoie [ client HTTP, csrfToken, entité User ].
     *
     * @return array{0: HttpClientInterface, 1: string, 2: \App\Entity\User}
     */
    protected function createAuthenticatedUser(
        string $email,
        string $password,
        array $extraData = []
    ): array {
        $client = static::createClient();

        // Dans ton projet, UserFactory::createOne() renvoie déjà un User, pas un Proxy
        $user = UserFactory::createOne(array_merge($extraData, [
            'email' => $email,
            'plainPassword' => $password,
        ]));

        // Login pour récupérer cookies + header X-CSRF-TOKEN
        $response = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        self::assertSame(200, $response->getStatusCode());

        $headers = $response->getHeaders(false);
        $csrfToken = $headers['x-csrf-token'][0] ?? null;
        self::assertNotNull($csrfToken, 'Expected X-CSRF-TOKEN header after /auth.');

        return [$client, $csrfToken, $user];
    }

    /**
     * Version admin : même chose mais avec rôles admin.
     *
     * @return array{0: HttpClientInterface, 1: string, 2: \App\Entity\User}
     */
    protected function createAuthenticatedAdmin(
        string $email,
        string $password,
        array $extraData = []
    ): array {
        // Forcer les rôles admin si non fournis
        $extraData['roles'] = $extraData['roles'] ?? ['ROLE_ADMIN', 'ROLE_USER'];

        return $this->createAuthenticatedUser(
            $email,
            $password,
            $extraData
        );
    }

    /**
     * Helper pour faire une requête protégée CSRF
     * sans que le client de test jette une exception sur 4xx.
     */
    protected function requestUnsafe(
        HttpClientInterface $client,
        string $method,
        string $uri,
        string $csrfToken,
        array $options = []
    ): ResponseInterface {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['X-CSRF-TOKEN' => $csrfToken]
        );

        return $client->request($method, $uri, $options);
    }
}

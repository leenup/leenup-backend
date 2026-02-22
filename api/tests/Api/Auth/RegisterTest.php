<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Zenstruck\Foundry\Test\Factories;

class RegisterTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    public function testRegister(): void
    {
        $client = self::createClient();
        $email = $this->uniqueEmail('newuser');

        $response = $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => $email,
            'firstName' => 'John',
            'lastName' => 'Doe',
        ]);

        $data = $response->toArray(false);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);

        // Vérifier qu'on peut se connecter avec ce nouvel utilisateur
        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => 'password123',
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        self::assertResponseIsSuccessful();

        // 👉 Plus de token dans le body, on vérifie juste que l'auth fonctionne
        $loginData = $loginResponse->toArray(false);
        self::assertArrayNotHasKey('token', $loginData);
    }

    public function testRegisterWithDuplicateEmail(): void
    {
        $email = $this->uniqueEmail('existing');
        UserFactory::createOne(['email' => $email]);

        $client = self::createClient();
        $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
                'firstName' => 'Jane',
                'lastName' => 'Doe',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This email is already in use',
                ],
            ],
        ]);
    }

    public function testRegisterWithoutPassword(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'firstName' => 'Test',
                'lastName' => 'User',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'plainPassword',
                    'message' => 'This value should not be blank.',
                ],
            ],
        ]);
    }

    public function testRegisterWithoutEmail(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'plainPassword' => 'password123',
                'firstName' => 'Test',
                'lastName' => 'User',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This value should not be blank.',
                ],
            ],
        ]);
    }

    public function testRegisterWithoutFirstName(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'plainPassword' => 'password123',
                'lastName' => 'User',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'firstName',
                    'message' => 'This value should not be blank.',
                ],
            ],
        ]);
    }

    public function testRegisterWithoutLastName(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'plainPassword' => 'password123',
                'firstName' => 'Test',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'lastName',
                    'message' => 'This value should not be blank.',
                ],
            ],
        ]);
    }

    public function testRegisterWithOptionalFields(): void
    {
        $client = self::createClient();
        $email = $this->uniqueEmail('user-with-bio');

        $response = $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
                'firstName' => 'Alice',
                'lastName' => 'Smith',
                'bio' => 'Passionate developer',
                'location' => 'Paris, France',
                'timezone' => 'Europe/Paris',
                'locale' => 'fr',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            '@type' => 'User',
            'email' => $email,
            'firstName' => 'Alice',
            'lastName' => 'Smith',
            'bio' => 'Passionate developer',
            'location' => 'Paris, France',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
        ]);

        $data = $response->toArray(false);
        self::assertArrayHasKey('id', $data);
    }

    public function testRegisterWithInvalidAvatarUrl(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'plainPassword' => 'password123',
                'firstName' => 'Test',
                'lastName' => 'User',
                'avatarUrl' => 'not-a-valid-url',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'avatarUrl',
                    'message' => 'Avatar path must start with /upload/',
                ],
            ],
        ]);
    }

    public function testRegisterWithTooLongFirstName(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'plainPassword' => 'password123',
                'firstName' => str_repeat('a', 101), // 101 caractères (max 100)
                'lastName' => 'User',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'firstName',
                    'message' => 'First name cannot be longer than 100 characters',
                ],
            ],
        ]);
    }

    public function testRegisterWithTooLongBio(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'test@example.com',
                'plainPassword' => 'password123',
                'firstName' => 'Test',
                'lastName' => 'User',
                'bio' => str_repeat('a', 501), // 501 caractères (max 500)
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'bio',
                    'message' => 'Bio cannot be longer than 500 characters',
                ],
            ],
        ]);
    }

    public function testUserCannotRegisterAsAdmin(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => 'admin-attempt@example.com',
                'plainPassword' => 'password123',
                'firstName' => 'Admin',
                'lastName' => 'Wannabe',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        // Route publique mais protégée par firewall JWT → 401 si pas de token/cookie d'auth
        self::assertResponseStatusCodeSame(401);
        self::assertJsonContains([
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUserCannotCreateAnotherAdmin(): void
    {
        $userEmail = $this->uniqueEmail('user');
        $newAdminEmail = $this->uniqueEmail('newadmin');

        UserFactory::createOne([
            'email' => $userEmail,
            'plainPassword' => 'password',
        ]);

        $client = self::createClient();

        // 1. Login
        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => $userEmail,
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 2. Récupérer le CSRF à partir de la réponse de login
        $headers = $loginResponse->getHeaders(false);
        $csrfToken = $headers['x-csrf-token'][0] ?? null;
        self::assertNotNull($csrfToken, 'Expected X-CSRF-TOKEN header after /auth.');

        // 3. Essayer de créer un admin en étant simple user (cookies + CSRF)
        $client->request('POST', '/register', [
            'json' => [
                'email' => $newAdminEmail,
                'plainPassword' => 'adminpassword123',
                'firstName' => 'New',
                'lastName' => 'Admin',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $csrfToken,
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            'detail' => 'Only admins can assign admin roles.',
        ]);
    }

    public function testAdminCanRegisterAdmin(): void
    {
        $adminEmail = $this->uniqueEmail('admin');
        $newAdminEmail = $this->uniqueEmail('newadmin');

        UserFactory::createOne([
            'email' => $adminEmail,
            'plainPassword' => 'password',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

        $client = self::createClient();

        // 1. Login admin
        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => $adminEmail,
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        // 2. CSRF à partir de la réponse de login
        $headers = $loginResponse->getHeaders(false);
        $csrfToken = $headers['x-csrf-token'][0] ?? null;
        self::assertNotNull($csrfToken, 'Expected X-CSRF-TOKEN header after /auth.');

        // 3. Créer un nouvel admin (cookies + CSRF)
        $client->request('POST', '/register', [
            'json' => [
                'email' => $newAdminEmail,
                'plainPassword' => 'adminpassword123',
                'firstName' => 'New',
                'lastName' => 'Admin',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $csrfToken,
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => $newAdminEmail,
            'firstName' => 'New',
            'lastName' => 'Admin',
            'roles' => [
                'ROLE_ADMIN',
                'ROLE_USER',
            ],
        ]);
    }
}

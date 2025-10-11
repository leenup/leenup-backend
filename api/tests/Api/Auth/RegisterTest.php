<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class RegisterTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        self::$counter++;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$counter = 0;
    }

    /**
     * Génère un email unique pour éviter les conflits en parallèle
     */
    private function generateUniqueEmail(string $prefix = 'test'): string
    {
        return $prefix . '-' . time() . '-' . self::$counter . '-' . uniqid() . '@example.com';
    }

    public function testRegister(): void
    {
        $client = self::createClient();
        $email = $this->generateUniqueEmail('newuser');

        $response = $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => $email,
        ]);

        $this->assertArrayNotHasKey('password', $response->toArray());
        $this->assertArrayNotHasKey('plainPassword', $response->toArray());

        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => 'password123',
            ],
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $loginResponse->toArray());
    }

    public function testRegisterWithDuplicateEmail(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        $email = $this->generateUniqueEmail('duplicate');

        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, 'password')
        );

        $em = $container->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
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

    public function testRegisterWithoutPassword(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => $this->generateUniqueEmail('nopass'),
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
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
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This value should not be blank.',
                ],
            ],
        ]);
    }

    public function testUserCannotRegisterAsAdmin(): void
    {
        static::createClient()->request('POST', '/register', [
            'json' => [
                'email' => $this->generateUniqueEmail('admin-attempt'),
                'plainPassword' => 'password123',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUserCannotCreateAnotherAdmin(): void
    {
        $userToken = $this->createAuthenticatedUser($this->generateUniqueEmail('user'), 'password');

        static::createClient()->request('POST', '/register', [
            'auth_bearer' => $userToken,
            'json' => [
                'email' => $this->generateUniqueEmail('newadmin'),
                'plainPassword' => 'adminpassword123',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            "detail" => "Only admins can assign admin roles."
        ]);
    }

    public function testAdminCanRegisterAdmin(): void
    {
        $adminToken = $this->createAuthenticatedAdmin($this->generateUniqueEmail('admin'), 'password');

        $newAdminEmail = $this->generateUniqueEmail('newadmin');

        static::createClient()->request('POST', '/register', [
            'auth_bearer' => $adminToken,
            'json' => [
                'email' => $newAdminEmail,
                'plainPassword' => 'adminpassword123',
                'roles' => ['ROLE_ADMIN'],
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $this->assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'email' => $newAdminEmail,
            'roles' => [
                'ROLE_ADMIN',
                'ROLE_USER',
            ],
        ]);
    }
}

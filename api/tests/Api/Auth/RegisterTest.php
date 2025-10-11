<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class RegisterTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    public function testRegister(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/register', [
            'json' => [
                'email' => 'newuser@example.com',
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
            'email' => 'newuser@example.com',
        ]);

        $this->assertArrayNotHasKey('password', $response->toArray());
        $this->assertArrayNotHasKey('plainPassword', $response->toArray());

        $loginResponse = $client->request('POST', '/auth', [
            'json' => [
                'email' => 'newuser@example.com',
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

        $user = new User();
        $user->setEmail('duplicate@example.com');
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, 'password')
        );

        $em = $container->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        $client->request('POST', '/register', [
            'json' => [
                'email' => 'duplicate@example.com',
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
                'email' => 'user@exemple.com',
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
                'email' => 'newuser@example.com',
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
        $userToken = $this->createAuthenticatedUser('user@exemple.com', 'password');

        static::createClient()->request('POST', '/register', [
            'auth_bearer' => $userToken,
            'json' => [
                'email' => 'newadmin@exemple.com',
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
        $adminToken = $this->createAuthenticatedAdmin('admin@example.com', 'password');

        static::createClient()->request('POST', '/register', [
            'auth_bearer' => $adminToken,
            'json' => [
                'email' => 'newadmin@exemple.com',
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
            'email' => 'newadmin@exemple.com',
            'roles' => [
                'ROLE_ADMIN',
                'ROLE_USER',
            ],
        ]);
    }
}

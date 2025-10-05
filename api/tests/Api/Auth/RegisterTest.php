<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;

class RegisterTest extends ApiTestCase
{
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

        // Vérifier que le mot de passe n'est pas retourné
        $this->assertArrayNotHasKey('password', $response->toArray());
        $this->assertArrayNotHasKey('plainPassword', $response->toArray());

        // Vérifier que l'utilisateur peut se connecter
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

        // Créer un premier utilisateur
        $user = new User();
        $user->setEmail('duplicate@example.com');
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, 'password')
        );

        $em = $container->get('doctrine')->getManager();
        $em->persist($user);
        $em->flush();

        // Tenter de créer un deuxième utilisateur avec le même email
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
            '@type' => 'ConstraintViolation',  // ← Changez ici
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This email is already in use',
                ],
            ],
        ]);
    }
}

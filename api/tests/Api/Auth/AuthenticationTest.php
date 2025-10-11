<?php

namespace App\Tests\Api\Auth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AuthenticationTest extends ApiTestCase
{
    // Traits fournis par Foundry pour gérer la BD de test
    use ResetDatabase, Factories;

    public function testLogin(): void
    {
        // Créer un utilisateur avec Foundry
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => '$3CR3T',
        ]);

        // Tester l'authentification
        $client = self::createClient();
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'test@example.com',
                'password' => '$3CR3T',
            ],
        ]);

        $json = $response->toArray();
        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $json);

        // Test non autorisé sans token
        $client->request('GET', '/categories');
        $this->assertResponseStatusCodeSame(401);

        // Test autorisé avec token
        $client->request('GET', '/categories', ['auth_bearer' => $json['token']]);
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        // Créer un utilisateur
        UserFactory::createOne([
            'email' => 'user@example.com',
            'plainPassword' => 'correct-password',
        ]);

        // Essayer de se connecter avec un mauvais mot de passe
        $client = self::createClient();
        $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
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

        $this->assertResponseStatusCodeSame(401);
    }
}

<?php

namespace App\Tests\Api\Trait;

use App\Entity\User;

trait AuthenticatedApiTestTrait
{
    private string $token;

    protected function createAuthenticatedUser(string $email = 'test@example.com', string $password = 'password'): string
    {
        $client = self::createClient();
        $container = self::getContainer();

        // Créer un utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, $password)
        );

        $manager = $container->get('doctrine')->getManager();
        $manager->persist($user);
        $manager->flush();

        // Obtenir le token JWT
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        return $response->toArray()['token'];
    }
}

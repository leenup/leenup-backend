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
        $em = $container->get('doctrine')->getManager();

        // Supprimer l'utilisateur s'il existe déjà
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        // Créer un utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, $password)
        );

        $em->persist($user);
        $em->flush();

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

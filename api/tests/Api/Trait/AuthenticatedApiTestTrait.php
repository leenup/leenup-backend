<?php

namespace App\Tests\Api\Trait;

use App\Entity\User;

trait AuthenticatedApiTestTrait
{
    protected function createAuthenticatedUser(
        string $email = 'test@example.com',
        string $password = 'password'
    ): string {
        return $this->createAuthenticated($email, $password, ['ROLE_USER']);
    }

    protected function createAuthenticatedAdmin(
        string $email = 'admin@example.com',
        string $password = 'admin123!'
    ): string {
        return $this->createAuthenticated($email, $password, ['ROLE_ADMIN']);
    }

    private function createAuthenticated(
        string $email,
        string $password,
        array $roles
    ): string {
        $client = self::createClient();
        $container = self::getContainer();
        $em = $container->get('doctrine')->getManager();

        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword(
            $container->get('security.user_password_hasher')->hashPassword($user, $password)
        );

        $em->persist($user);
        $em->flush();

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

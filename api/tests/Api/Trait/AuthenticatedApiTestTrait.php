<?php

namespace App\Tests\Api\Trait;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait AuthenticatedApiTestTrait
{
    /**
     * @return array{0: \Symfony\Contracts\HttpClient\HttpClientInterface, 1: string, 2: User}
     */
    protected function createAuthenticatedUser(
        string $email = 'test@example.com',
        string $password = 'password'
    ): array {
        return $this->createAuthenticated($email, $password, ['ROLE_USER']);
    }

    /**
     * @return array{0: \Symfony\Contracts\HttpClient\HttpClientInterface, 1: string, 2: User}
     */
    protected function createAuthenticatedAdmin(
        string $email = 'admin@example.com',
        string $password = 'admin123!'
    ): array {
        return $this->createAuthenticated($email, $password, ['ROLE_ADMIN']);
    }

    /**
     * Crée un user + fait un /auth + renvoie (client, csrfToken, user).
     *
     * @return array{0: \Symfony\Contracts\HttpClient\HttpClientInterface, 1: string, 2: User}
     */
    private function createAuthenticated(
        string $email,
        string $password,
        array $roles
    ): array {
        $client = self::createClient();
        $container = self::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')->getManager();
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        // On nettoie un éventuel user existant pour ce mail
        $existingUser = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $em->remove($existingUser);
            $em->flush();
        }

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $em->persist($user);
        $em->flush();

        /** @var ResponseInterface $response */
        $response = $client->request('POST', '/auth', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        self::assertResponseIsSuccessful();

        $headers = $response->getHeaders(false);
        $csrfToken = $headers['x-csrf-token'][0] ?? null;

        self::assertNotNull($csrfToken, 'CSRF token header "X-CSRF-TOKEN" should be present after /auth.');

        return [$client, $csrfToken, $user];
    }

    /**
     * Helper pour requêtes NON sûres (POST/PUT/PATCH/DELETE) avec CSRF.
     */
    protected function requestUnsafe(
        $client,
        string $method,
        string $uri,
        string $csrfToken,
        array $options = []
    ): ResponseInterface {
        $options['headers']['X-CSRF-TOKEN'] = $csrfToken;

        return $client->request($method, $uri, $options);
    }
}

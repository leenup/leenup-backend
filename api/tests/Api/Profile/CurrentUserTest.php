<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class CurrentUserTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->createAuthenticatedUser('current-user-test@example.com', 'password123');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'current-user-test@example.com',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);

        // Vérifier que le mot de passe n'est pas exposé
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    public function testGetCurrentUserProfileWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCurrentUserProfileWithInvalidToken(): void
    {
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => 'invalid_token_12345',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== Vérifier que chaque utilisateur voit son propre profil ====================

    public function testDifferentUsersGetTheirOwnProfile(): void
    {
        // Créer un deuxième utilisateur
        $token2 = $this->createAuthenticatedUser('second-user@example.com', 'password456');

        // Premier utilisateur récupère son profil
        $response1 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();
        $this->assertEquals('current-user-test@example.com', $data1['email']);

        // Deuxième utilisateur récupère son profil
        $response2 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $data2 = $response2->toArray();
        $this->assertEquals('second-user@example.com', $data2['email']);

        // Vérifier que les IDs sont différents
        $this->assertNotEquals($data1['id'], $data2['id']);
    }

    // ==================== Vérifier la structure de la réponse ====================

    public function testCurrentUserProfileResponseStructure(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Vérifier la présence des champs obligatoires
        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);

        // Vérifier les types
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['email']);
        $this->assertIsArray($data['roles']);

        // Vérifier que @type est correct
        $this->assertEquals('User', $data['@type']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'updated-email@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'updated-email@example.com',
        ]);

        // Générer un nouveau token avec le nouvel email
        $client = static::createClient();
        $tokenResponse = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'updated-email@example.com',
                'password' => 'password123',
            ],
        ]);
        $newToken = $tokenResponse->toArray()['token'];

        // Vérifier que la modification est persistée avec le nouveau token
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $newToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'updated-email@example.com',
        ]);
    }

    public function testUpdateCurrentUserEmailWithoutAuthentication(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'json' => [
                'email' => 'hacker@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateCurrentUserEmailWithInvalidEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'invalid-email-format',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailWithDuplicateEmail(): void
    {
        // Créer un deuxième utilisateur
        $this->createAuthenticatedUser('existing-user@example.com', 'password');

        // Essayer de mettre à jour avec un email déjà utilisé
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'existing-user@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                    'message' => 'This email is already in use',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => '',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                [
                    'propertyPath' => 'email',
                ],
            ],
        ]);
    }

    public function testUpdateCurrentUserEmailMultipleTimes(): void
    {
        $client = static::createClient();

        // Première mise à jour
        $client->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'first-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'first-update@example.com',
        ]);

        // Obtenir un nouveau token avec le premier email modifié
        $tokenResponse1 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'first-update@example.com',
                'password' => 'password123',
            ],
        ]);
        $token1 = $tokenResponse1->toArray()['token'];

        // Deuxième mise à jour avec le nouveau token
        $client->request('PATCH', '/me', [
            'auth_bearer' => $token1,
            'json' => [
                'email' => 'second-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'second-update@example.com',
        ]);

        // Obtenir un nouveau token avec le second email modifié
        $tokenResponse2 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => 'second-update@example.com',
                'password' => 'password123',
            ],
        ]);
        $token2 = $tokenResponse2->toArray()['token'];

        // Vérifier que c'est bien la dernière valeur qui est persistée
        $client->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'second-update@example.com',
        ]);
    }

    public function testUpdateCurrentUserDoesNotAffectOtherUsers(): void
    {
        // Créer un deuxième utilisateur
        $token2 = $this->createAuthenticatedUser('other-user@example.com', 'password');

        // Le premier utilisateur modifie son email
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'updated-first-user@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier que le deuxième utilisateur n'a pas été affecté
        $response2 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'other-user@example.com',
        ]);
    }

    public function testUpdateCurrentUserEmailDoesNotExposePassword(): void
    {
        $response = static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->token,
            'json' => [
                'email' => 'secure-update@example.com',
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Vérifier que le mot de passe n'est jamais exposé
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
    }

    // ==================== DELETE /me ====================

    public function testDeleteCurrentUserAccount(): void
    {
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Essayer d'utiliser le même token après suppression
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        $email = 'current-user-test@example.com';
        $password = 'password123';

        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Essayer de se reconnecter avec les anciens identifiants
        static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithoutAuthentication(): void
    {
        static::createClient()->request('DELETE', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithInvalidToken(): void
    {
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => 'invalid_token_12345',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountDoesNotAffectOtherUsers(): void
    {
        // Créer un deuxième utilisateur
        $token2 = $this->createAuthenticatedUser('other-user-delete@example.com', 'password');

        // Le premier utilisateur supprime son compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => 'other-user-delete@example.com',
        ]);
    }

    public function testDeleteCurrentUserAccountIsIdempotent(): void
    {
        // Première suppression
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Deuxième tentative de suppression avec le même token
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        // Le token est invalide car l'utilisateur n'existe plus
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountCannotBeUndone(): void
    {
        $email = 'current-user-test@example.com';
        $password = 'password123';

        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que l'utilisateur n'existe plus en base
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        $this->assertNull($user, 'L\'utilisateur doit être supprimé de la base de données');

        // Impossible de se reconnecter
        static::createClient()->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}

<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class CurrentUserTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static string $token;
    private static string $email = 'current-user-test@example.com';
    private static string $password = 'password123';
    private static bool $initialized = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer le token une seule fois pour toute la classe
        if (!self::$initialized) {
            self::$token = $this->createAuthenticatedUser(self::$email, self::$password);
            self::$initialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$initialized = false;
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => self::$email,
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
        // Créer un deuxième utilisateur pour ce test spécifique
        $token2 = $this->createAuthenticatedUser('second-user@example.com', 'password456');

        // Premier utilisateur récupère son profil
        $response1 = static::createClient()->request('GET', '/me', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data1 = $response1->toArray();
        $this->assertEquals(self::$email, $data1['email']);

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
            'auth_bearer' => self::$token,
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
        // Créer un utilisateur spécifique pour ce test
        $testToken = $this->createAuthenticatedUser('update-email-test@example.com', 'pass123');
        $newEmail = 'updated-email-' . time() . '@example.com';

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
            'json' => [
                'email' => $newEmail,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => $newEmail,
        ]);

        // Générer un nouveau token avec le nouvel email
        $client = static::createClient();
        $tokenResponse = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $newEmail,
                'password' => 'pass123',
            ],
        ]);
        $newToken = $tokenResponse->toArray()['token'];

        // Vérifier que la modification est persistée avec le nouveau token
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $newToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => $newEmail,
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
        $testToken = $this->createAuthenticatedUser('invalid-email-test@example.com', 'pass123');

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
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
        $timestamp = time();

        // Créer un utilisateur existant
        $existingEmail = "existing-user-{$timestamp}@example.com";
        $this->createAuthenticatedUser($existingEmail, 'password');

        // Créer l'utilisateur qui va essayer de prendre l'email
        $testToken = $this->createAuthenticatedUser("test-duplicate-{$timestamp}@example.com", 'pass123');

        // Essayer de mettre à jour avec un email déjà utilisé
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
            'json' => [
                'email' => $existingEmail, // ✅ Utiliser la variable
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

    public function testUpdateCurrentUserEmailMultipleTimes(): void
    {
        $client = static::createClient();
        $timestamp = time();

        $initialEmail = "multi-update-{$timestamp}@example.com";
        $testToken = $this->createAuthenticatedUser($initialEmail, 'pass123');

        // Première mise à jour
        $firstEmail = "first-update-{$timestamp}@example.com";
        $response1 = $client->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
            'json' => [
                'email' => $firstEmail,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data1 = $response1->toArray();
        $this->assertEquals($firstEmail, $data1['email']);

        // Obtenir un nouveau token avec le premier email modifié
        $tokenResponse1 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $firstEmail,
                'password' => 'pass123',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $tokenResponse1->toArray());
        $token1 = $tokenResponse1->toArray()['token'];

        // Deuxième mise à jour avec le nouveau token
        $secondEmail = "second-update-{$timestamp}@example.com";
        $response2 = $client->request('PATCH', '/me', [
            'auth_bearer' => $token1,
            'json' => [
                'email' => $secondEmail,
            ],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data2 = $response2->toArray();
        $this->assertEquals($secondEmail, $data2['email']);

        // Obtenir un nouveau token avec le second email modifié
        $tokenResponse2 = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'email' => $secondEmail,
                'password' => 'pass123',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $tokenResponse2->toArray());
        $token2 = $tokenResponse2->toArray()['token'];

        // Vérifier que c'est bien la dernière valeur qui est persistée
        $finalResponse = $client->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();

        // ✅ Vérifier l'email dans la réponse GET /me
        $finalData = $finalResponse->toArray();
        $this->assertEquals($secondEmail, $finalData['email']);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        $testToken = $this->createAuthenticatedUser('empty-email-test@example.com', 'pass123');

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
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

    public function testUpdateCurrentUserDoesNotAffectOtherUsers(): void
    {
        // Créer deux utilisateurs
        $timestamp = time();
        $token1 = $this->createAuthenticatedUser("user1-{$timestamp}@example.com", 'password');
        $token2 = $this->createAuthenticatedUser("user2-{$timestamp}@example.com", 'password');

        // Le premier utilisateur modifie son email
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $token1,
            'json' => [
                'email' => "updated-user1-{$timestamp}@example.com",
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
            'email' => "user2-{$timestamp}@example.com",
        ]);
    }

    public function testUpdateCurrentUserEmailDoesNotExposePassword(): void
    {
        $testToken = $this->createAuthenticatedUser('secure-update@example.com', 'pass123');
        $newEmail = 'secure-update-new-' . time() . '@example.com';

        $response = static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $testToken,
            'json' => [
                'email' => $newEmail,
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
        $testToken = $this->createAuthenticatedUser('delete-account@example.com', 'pass123');

        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        $testToken = $this->createAuthenticatedUser('delete-token-invalid@example.com', 'pass123');

        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Essayer d'utiliser le même token après suppression
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $testToken,
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        $email = 'delete-prevents-login-' . time() . '@example.com';
        $password = 'pass123';
        $testToken = $this->createAuthenticatedUser($email, $password);

        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
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
        $timestamp = time();

        // Créer deux utilisateurs
        $token1 = $this->createAuthenticatedUser("delete-user1-{$timestamp}@example.com", 'password');
        $token2 = $this->createAuthenticatedUser("delete-user2-{$timestamp}@example.com", 'password');

        // Le premier utilisateur supprime son compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $token1,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Le deuxième utilisateur peut toujours accéder à son profil
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => $token2,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'email' => "delete-user2-{$timestamp}@example.com",
        ]);
    }

    public function testDeleteCurrentUserAccountIsIdempotent(): void
    {
        $testToken = $this->createAuthenticatedUser('delete-idempotent@example.com', 'pass123');

        // Première suppression
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Deuxième tentative de suppression avec le même token
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
        ]);

        // Le token est invalide car l'utilisateur n'existe plus
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountCannotBeUndone(): void
    {
        $email = 'delete-cannot-undo-' . time() . '@example.com';
        $password = 'pass123';
        $testToken = $this->createAuthenticatedUser($email, $password);

        // Supprimer le compte
        static::createClient()->request('DELETE', '/me', [
            'auth_bearer' => $testToken,
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

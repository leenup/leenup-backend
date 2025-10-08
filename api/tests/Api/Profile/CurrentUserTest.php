<?php
// api/tests/Api/Profile/CurrentUserTest.php

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
}

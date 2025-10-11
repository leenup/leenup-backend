<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class UserTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userToken = $this->createAuthenticatedUser('user@user@exemple.com', 'user123!');
        $this->adminToken = $this->createAuthenticatedAdmin('admin@admin@exemple.com', 'admin123!');
    }

    public function testGetUsersAsAdmin(): void
    {
        $this->createAuthenticatedUser('user2@exemple.com', 'user123!');
        $this->createAuthenticatedUser('user3@exemple.com', 'user123!');

        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => $this->adminToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/User', $data['@context']);
        $this->assertSame('Collection', $data['@type']);
        $this->assertArrayHasKey('member', $data);
        $this->assertGreaterThanOrEqual(2, $data['totalItems']);

        $emails = array_column($data['member'], 'email');
        $this->assertContains('user@user@exemple.com', $emails);
        $this->assertContains('admin@admin@exemple.com', $emails);

        $admin = array_filter($data['member'], fn($u) => in_array('ROLE_ADMIN', $u['roles']));
        $this->assertNotEmpty($admin, 'Aucun utilisateur admin trouvé dans la liste');
    }

    public function testGetUsersAsUserForbidden(): void
    {
        $response = static::createClient()->request('GET', '/users', [
            'auth_bearer' => $this->userToken,
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/Error', $data['@context']);
        $this->assertSame('Error', $data['@type']);
        $this->assertSame(403, $data['status']);
        $this->assertSame('Only admins can list users.', $data['detail']);
    }
}

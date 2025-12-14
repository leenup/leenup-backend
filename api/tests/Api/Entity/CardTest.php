<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\CardFactory;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class CardTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $userClient;
    private HttpClientInterface $adminClient;

    private string $userCsrfToken;
    private string $adminCsrfToken;

    private User $user;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Authentification d'un user normal
        [
            $this->userClient,
            $this->userCsrfToken,
            $this->user,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-card'),
            password: 'password',
        );

        // Authentification d'un admin
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->admin,
        ] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-card'),
            password: 'password'
        );
    }

    // ========================================
    // TESTS DE LECTURE
    // ========================================

    public function testUserCanViewActiveCard(): void
    {
        $card = CardFactory::createOne([
            'code' => 'CARD_001',
            'family' => 'Attack',
            'title' => 'Dragon Strike',
            'subtitle' => 'A powerful fire attack',
            'description' => 'Deals 50 damage to the opponent',
            'category' => 'Fire',
            'level' => 3,
            'imageUrl' => 'https://example.com/dragon.png',
            'isActive' => true,
        ]);

        $response = $this->userClient->request('GET', '/cards/'.$card->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Dragon Strike', $data['title'] ?? null);
        self::assertSame('CARD_001', $data['code'] ?? null);
        self::assertSame(3, $data['level'] ?? null);
    }

    public function testUserCannotViewInactiveCard(): void
    {
        $card = CardFactory::createOne([
            'code' => 'CARD_INACTIVE',
            'family' => 'Support',
            'title' => 'Inactive Card',
            'category' => 'Support',
            'level' => 1,
            'imageUrl' => 'https://example.com/inactive.png',
            'isActive' => false,
        ]);

        $response = $this->userClient->request('GET', '/cards/'.$card->getId());

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
    }

    public function testAdminCanViewInactiveCard(): void
    {
        $card = CardFactory::createOne([
            'code' => 'CARD_INACTIVE_ADMIN',
            'family' => 'Support',
            'title' => 'Admin Inactive Card',
            'category' => 'Support',
            'level' => 2,
            'imageUrl' => 'https://example.com/admin-inactive.png',
            'isActive' => false,
        ]);

        $response = $this->adminClient->request('GET', '/cards/'.$card->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Admin Inactive Card', $data['title'] ?? null);
    }
}

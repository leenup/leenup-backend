<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class CategoriesTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->createAuthenticatedUser('category-test@example.com');
    }

    public function testCreateCategory(): void
    {
        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Développement',
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Category',
            'title' => 'Développement',
        ]);
    }
}

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

    protected function tearDown(): void
    {
        parent::tearDown();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Category')->execute();
    }

    private function createCategory(string $title): array
    {
        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => $title],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray();
    }

    // ==================== CRUD Operations ====================

    public function testGetCategories(): void
    {
        $this->createCategory('IT');
        $this->createCategory('Finance');
        $this->createCategory('Operations');

        $response = static::createClient()->request('GET', '/categories', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Collection',
            'totalItems' => 3,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertContains('IT', $titles);
        $this->assertContains('Finance', $titles);
        $this->assertContains('Operations', $titles);
    }

    public function testCreateCategory(): void
    {
        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Development'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Category',
            'title' => 'Development',
        ]);
    }

    public function testGetCategory(): void
    {
        $category = $this->createCategory('Design');

        static::createClient()->request('GET', "/categories/{$category['id']}", [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Design']);
    }

    public function testUpdateCategory(): void
    {
        $category = $this->createCategory('Marketing');

        static::createClient()->request('PATCH', "/categories/{$category['id']}", [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Digital Marketing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Digital Marketing']);
    }

    public function testDeleteCategory(): void
    {
        $category = $this->createCategory('HR');

        static::createClient()->request('DELETE', "/categories/{$category['id']}", [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    // ==================== Validations ====================

    public function testCreateCategoryWithBlankTitle(): void
    {
        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => ''],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'The title cannot be blank'],
            ],
        ]);
    }

    public function testCreateCategoryWithTitleTooShort(): void
    {
        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'A'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title'],
            ],
        ]);
    }

    public function testCreateCategoryWithDuplicateTitle(): void
    {
        $this->createCategory('Sales');

        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Sales'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'This category already exists'],
            ],
        ]);
    }

    // ==================== Filters ====================

    public function testFilterCategoriesByTitle(): void
    {
        $this->createCategory('Development');
        $this->createCategory('Design');
        $this->createCategory('DevOps');

        $response = static::createClient()->request('GET', '/categories?title=Development', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(1, $data['member']);
        $this->assertEquals('Development', $data['member'][0]['title']);
    }

    public function testFilterCategoriesByTitlePartial(): void
    {
        $this->createCategory('Web Development');
        $this->createCategory('Mobile Development');
        $this->createCategory('Design');

        $response = static::createClient()->request('GET', '/categories?title=Development', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(2, $data['member']);
        $titles = array_column($data['member'], 'title');
        $this->assertContains('Web Development', $titles);
        $this->assertContains('Mobile Development', $titles);
    }

    public function testOrderCategoriesByTitle(): void
    {
        $this->createCategory('Marketing');
        $this->createCategory('Finance');
        $this->createCategory('HR');

        $response = static::createClient()->request('GET', '/categories?order[title]=asc', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertEquals(['Finance', 'HR', 'Marketing'], $titles);
    }
}

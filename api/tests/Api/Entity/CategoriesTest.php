<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class CategoriesTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static string $token;
    private static bool $initialized = false;
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            self::$token = $this->createAuthenticatedUser('category-test@example.com');
            self::$initialized = true;
        }

        self::$counter++;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$initialized = false;
        self::$counter = 0;
    }

    private function createCategory(string $title): array
    {
        $uniqueTitle = self::$counter . '_' . $title;

        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniqueTitle],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray();
    }

    // ==================== CRUD Operations ====================

    public function testGetCategories(): void
    {
        $cat1 = $this->createCategory('IT');
        $cat2 = $this->createCategory('Finance');
        $cat3 = $this->createCategory('Operations');

        $response = static::createClient()->request('GET', '/categories', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertContains($cat1['title'], $titles);
        $this->assertContains($cat2['title'], $titles);
        $this->assertContains($cat3['title'], $titles);
    }

    public function testCreateCategory(): void
    {
        $uniqueTitle = self::$counter . '_Development';

        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniqueTitle],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Category',
            'title' => $uniqueTitle,
        ]);
    }

    public function testGetCategory(): void
    {
        $category = $this->createCategory('Design');

        $response = static::createClient()->request('GET', "/categories/{$category['id']}", [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => $category['title']]);
    }

    public function testUpdateCategory(): void
    {
        $category = $this->createCategory('Marketing');
        $newTitle = self::$counter . '_Digital Marketing';

        static::createClient()->request('PATCH', "/categories/{$category['id']}", [
            'auth_bearer' => self::$token,
            'json' => ['title' => $newTitle],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => $newTitle]);
    }

    public function testDeleteCategory(): void
    {
        $category = $this->createCategory('HR');

        static::createClient()->request('DELETE', "/categories/{$category['id']}", [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    // ==================== Validations ====================

    public function testCreateCategoryWithBlankTitle(): void
    {
        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
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
            'auth_bearer' => self::$token,
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
        $title = self::$counter . '_Sales';

        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $title],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $title],
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
        $cat1 = $this->createCategory('Development');
        $this->createCategory('Design');
        $this->createCategory('DevOps');

        $response = static::createClient()->request('GET', '/categories?title=' . $cat1['title'], [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(1, $data['member']);
        $this->assertEquals($cat1['title'], $data['member'][0]['title']);
    }

    public function testFilterCategoriesByTitlePartial(): void
    {
        $cat1 = $this->createCategory('Web Development');
        $cat2 = $this->createCategory('Mobile Development');
        $this->createCategory('Design');

        // ✅ Filtrer avec un terme unique au test actuel
        $searchTerm = self::$counter . '_';
        $response = static::createClient()->request('GET', '/categories?title=' . $searchTerm, [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // ✅ On doit avoir au moins les 3 catégories de ce test
        $this->assertGreaterThanOrEqual(3, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        $this->assertContains($cat1['title'], $titles);
        $this->assertContains($cat2['title'], $titles);
    }

    public function testOrderCategoriesByTitle(): void
    {
        // Utiliser un préfixe unique pour ce test
        $uniquePrefix = 'sort-' . self::$counter . '-';

        $cat1 = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniquePrefix . 'Zebra'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ])->toArray();

        $cat2 = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniquePrefix . 'Apple'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ])->toArray();

        $cat3 = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniquePrefix . 'Mango'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ])->toArray();

        // Filtrer uniquement les catégories de ce test avec le préfixe unique
        $response = static::createClient()->request('GET', '/categories?title=' . $uniquePrefix . '&order[title]=asc', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        // Vérifier l'ordre alphabétique
        $this->assertEquals([
            $cat2['title'], // Apple
            $cat3['title'], // Mango
            $cat1['title'], // Zebra
        ], $titles);
    }
}

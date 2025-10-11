<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\CategoryFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CategoriesTest extends ApiTestCase
{
    use ResetDatabase, Factories;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur authentifié pour chaque test
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->token = $response->toArray()['token'];
    }

    // ==================== CRUD Operations ====================

    public function testGetCategories(): void
    {
        // Créer des catégories avec Foundry
        CategoryFactory::createOne(['title' => 'IT']);
        CategoryFactory::createOne(['title' => 'Finance']);
        CategoryFactory::createOne(['title' => 'Operations']);

        $response = static::createClient()->request('GET', '/categories', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Category',
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
        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Development'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Category',
            'title' => 'Development',
        ]);
        $this->assertMatchesRegularExpression('~^/categories/\d+$~', $response->toArray()['@id']);
    }

    public function testGetCategory(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Design']);

        $response = static::createClient()->request('GET', '/categories/' . $category->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Category',
            'title' => 'Design',
        ]);
    }

    public function testUpdateCategory(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Marketing']);

        static::createClient()->request('PATCH', '/categories/' . $category->getId(), [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Digital Marketing'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Digital Marketing']);
    }

    public function testDeleteCategory(): void
    {
        $category = CategoryFactory::createOne(['title' => 'HR']);

        static::createClient()->request('DELETE', '/categories/' . $category->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que la catégorie n'existe plus
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(\App\Entity\Category::class)->findOneBy(['title' => 'HR'])
        );
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
            '@type' => 'ConstraintViolation',
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
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title'],
            ],
        ]);
    }

    public function testCreateCategoryWithDuplicateTitle(): void
    {
        CategoryFactory::createOne(['title' => 'Sales']);

        static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'Sales'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'This category already exists'],
            ],
        ]);
    }

    // ==================== Filters ====================

    public function testFilterCategoriesByTitle(): void
    {
        CategoryFactory::createOne(['title' => 'Development']);
        CategoryFactory::createOne(['title' => 'Design']);
        CategoryFactory::createOne(['title' => 'DevOps']);

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
        CategoryFactory::createOne(['title' => 'Web Development']);
        CategoryFactory::createOne(['title' => 'Mobile Development']);
        CategoryFactory::createOne(['title' => 'Design']);

        $response = static::createClient()->request('GET', '/categories?title=Development', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        $this->assertContains('Web Development', $titles);
        $this->assertContains('Mobile Development', $titles);
    }

    public function testOrderCategoriesByTitle(): void
    {
        CategoryFactory::createOne(['title' => 'Zebra']);
        CategoryFactory::createOne(['title' => 'Apple']);
        CategoryFactory::createOne(['title' => 'Mango']);

        $response = static::createClient()->request('GET', '/categories?order[title]=asc', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        // Vérifier l'ordre alphabétique
        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }

    public function testOrderCategoriesByTitleDesc(): void
    {
        CategoryFactory::createOne(['title' => 'Zebra']);
        CategoryFactory::createOne(['title' => 'Apple']);
        CategoryFactory::createOne(['title' => 'Mango']);

        $response = static::createClient()->request('GET', '/categories?order[title]=desc', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        // Vérifier l'ordre inverse
        $this->assertEquals(['Zebra', 'Mango', 'Apple'], $titles);
    }
}

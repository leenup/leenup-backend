<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Factory\CategoryFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class CategoriesTest extends ApiTestCase
{
    use Factories;

    private Client $userClient;
    private Client $adminClient;

    private string $userCsrfToken;
    private string $adminCsrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        // === Création des users ===
        UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        UserFactory::createOne([
            'email' => 'admin@exemple.com',
            'plainPassword' => 'adminpassword',
            'roles' => ['ROLE_ADMIN'],
        ]);

        // === Client USER authentifié ===
        $this->userClient = static::createClient();
        $userAuthResponse = $this->userClient->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        $userHeaders = $userAuthResponse->getHeaders(false);
        $this->userCsrfToken = $userHeaders['x-csrf-token'][0] ?? '';
        $this->assertNotSame('', $this->userCsrfToken, 'Missing X-CSRF-TOKEN header for user.');

        // === Client ADMIN authentifié ===
        $this->adminClient = static::createClient();
        $adminAuthResponse = $this->adminClient->request('POST', '/auth', [
            'json' => [
                'email' => 'admin@exemple.com',
                'password' => 'adminpassword',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        $adminHeaders = $adminAuthResponse->getHeaders(false);
        $this->adminCsrfToken = $adminHeaders['x-csrf-token'][0] ?? '';
        $this->assertNotSame('', $this->adminCsrfToken, 'Missing X-CSRF-TOKEN header for admin.');
    }

    // ==================== GET Operations ====================

    public function testGetCategoriesAsUser(): void
    {
        CategoryFactory::createOne(['title' => 'IT']);
        CategoryFactory::createOne(['title' => 'Finance']);
        CategoryFactory::createOne(['title' => 'Operations']);

        $response = $this->userClient->request('GET', '/categories');

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

    public function testGetCategoriesAsAdmin(): void
    {
        CategoryFactory::createOne(['title' => 'IT']);
        CategoryFactory::createOne(['title' => 'Finance']);
        CategoryFactory::createOne(['title' => 'Operations']);

        $response = $this->adminClient->request('GET', '/categories');

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

    public function testGetCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Design']);

        $response = $this->userClient->request('GET', '/categories/' . $category->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Category',
            'title' => 'Design',
        ]);
    }

    public function testGetCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Design']);

        $response = $this->adminClient->request('GET', '/categories/' . $category->getId());

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Category',
            'title' => 'Design',
        ]);
    }

    // ==================== POST Operations ====================

    public function testCreateCategoryAsUser(): void
    {
        $this->userClient->request('POST', '/categories', [
            'json' => ['title' => 'Development'],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $this->userCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testCreateCategoryAsAdmin(): void
    {
        $response = $this->adminClient->request('POST', '/categories', [
            'json' => ['title' => 'Development'],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
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

    // ==================== PATCH Operations ====================

    public function testUpdateCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Marketing']);

        $this->userClient->request('PATCH', '/categories/' . $category->getId(), [
            'json' => ['title' => 'Digital Marketing'],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-CSRF-TOKEN' => $this->userCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testUpdateCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Marketing']);

        $this->adminClient->request('PATCH', '/categories/' . $category->getId(), [
            'json' => ['title' => 'Digital Marketing'],
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Digital Marketing']);
    }

    // ==================== DELETE Operations ====================

    public function testDeleteCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'HR']);

        $this->userClient->request('DELETE', '/categories/' . $category->getId(), [
            'headers' => [
                'X-CSRF-TOKEN' => $this->userCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testDeleteCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'HR']);

        $this->adminClient->request('DELETE', '/categories/' . $category->getId(), [
            'headers' => [
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(\App\Entity\Category::class)->findOneBy(['title' => 'HR'])
        );
    }

    // ==================== Validations ====================

    public function testCreateCategoryWithBlankTitleAsAdmin(): void
    {
        $this->adminClient->request('POST', '/categories', [
            'json' => ['title' => ''],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'The title cannot be blank'],
            ],
        ]);
    }

    public function testCreateCategoryWithTitleTooShortAsAdmin(): void
    {
        $this->adminClient->request('POST', '/categories', [
            'json' => ['title' => 'A'],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title'],
            ],
        ]);
    }

    public function testCreateCategoryWithDuplicateTitleAsAdmin(): void
    {
        CategoryFactory::createOne(['title' => 'Sales']);

        $this->adminClient->request('POST', '/categories', [
            'json' => ['title' => 'Sales'],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'X-CSRF-TOKEN' => $this->adminCsrfToken,
            ],
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

        $response = $this->userClient->request('GET', '/categories?title=Development');

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

        $response = $this->userClient->request('GET', '/categories?title=Development');

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

        $response = $this->userClient->request('GET', '/categories?order[title]=asc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }

    public function testOrderCategoriesByTitleDesc(): void
    {
        CategoryFactory::createOne(['title' => 'Zebra']);
        CategoryFactory::createOne(['title' => 'Apple']);
        CategoryFactory::createOne(['title' => 'Mango']);

        $response = $this->userClient->request('GET', '/categories?order[title]=desc');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        $this->assertEquals(['Zebra', 'Mango', 'Apple'], $titles);
    }
}

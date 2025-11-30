<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\CategoryFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Zenstruck\Foundry\Test\Factories;

class CategoriesTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    // ==================== GET Operations ====================

    public function testGetCategoriesAsUser(): void
    {
        CategoryFactory::createOne(['title' => 'IT']);
        CategoryFactory::createOne(['title' => 'Finance']);
        CategoryFactory::createOne(['title' => 'Operations']);

        // Client user authentifié
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-categories@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories');

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Collection',
            'totalItems' => 3,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        self::assertContains('IT', $titles);
        self::assertContains('Finance', $titles);
        self::assertContains('Operations', $titles);
    }

    public function testGetCategoriesAsAdmin(): void
    {
        CategoryFactory::createOne(['title' => 'IT']);
        CategoryFactory::createOne(['title' => 'Finance']);
        CategoryFactory::createOne(['title' => 'Operations']);

        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-categories@example.com',
            password: 'adminpassword',
        );

        $response = $client->request('GET', '/categories');

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Collection',
            'totalItems' => 3,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        self::assertContains('IT', $titles);
        self::assertContains('Finance', $titles);
        self::assertContains('Operations', $titles);
    }

    public function testGetCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Design']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-get-category@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories/'.$category->getId());

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@type' => 'Category',
            'title' => 'Design',
        ]);
    }

    public function testGetCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Design']);

        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-get-category@example.com',
            password: 'adminpassword',
        );

        $response = $client->request('GET', '/categories/'.$category->getId());

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@type' => 'Category',
            'title' => 'Design',
        ]);
    }

    // ==================== POST Operations ====================

    public function testCreateCategoryAsUser(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-create-category@example.com',
            password: 'password',
        );

        $this->requestUnsafe(
            $client,
            'POST',
            '/categories',
            $csrfToken,
            [
                'json' => ['title' => 'Development'],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testCreateCategoryAsAdmin(): void
    {
        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-create-category@example.com',
            password: 'adminpassword',
        );

        $response = $this->requestUnsafe(
            $client,
            'POST',
            '/categories',
            $csrfToken,
            [
                'json' => ['title' => 'Development'],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@context' => '/contexts/Category',
            '@type' => 'Category',
            'title' => 'Development',
        ]);
        self::assertMatchesRegularExpression('~^/categories/\d+$~', $response->toArray()['@id']);
    }

    // ==================== PATCH Operations ====================

    public function testUpdateCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Marketing']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-update-category@example.com',
            password: 'password',
        );

        $this->requestUnsafe(
            $client,
            'PATCH',
            '/categories/'.$category->getId(),
            $csrfToken,
            [
                'json' => ['title' => 'Digital Marketing'],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testUpdateCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'Marketing']);

        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-update-category@example.com',
            password: 'adminpassword',
        );

        $this->requestUnsafe(
            $client,
            'PATCH',
            '/categories/'.$category->getId(),
            $csrfToken,
            [
                'json' => ['title' => 'Digital Marketing'],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['title' => 'Digital Marketing']);
    }

    // ==================== DELETE Operations ====================

    public function testDeleteCategoryAsUser(): void
    {
        $category = CategoryFactory::createOne(['title' => 'HR']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-delete-category@example.com',
            password: 'password',
        );

        $this->requestUnsafe(
            $client,
            'DELETE',
            '/categories/'.$category->getId(),
            $csrfToken
        );

        self::assertResponseStatusCodeSame(403);
        self::assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testDeleteCategoryAsAdmin(): void
    {
        $category = CategoryFactory::createOne(['title' => 'HR']);

        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-delete-category@example.com',
            password: 'adminpassword',
        );

        $this->requestUnsafe(
            $client,
            'DELETE',
            '/categories/'.$category->getId(),
            $csrfToken
        );

        self::assertResponseStatusCodeSame(204);

        self::assertNull(
            static::getContainer()->get('doctrine')->getRepository(\App\Entity\Category::class)->findOneBy(['title' => 'HR'])
        );
    }

    // ==================== Validations ====================

    public function testCreateCategoryWithBlankTitleAsAdmin(): void
    {
        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-blank-title@example.com',
            password: 'adminpassword',
        );

        $this->requestUnsafe(
            $client,
            'POST',
            '/categories',
            $csrfToken,
            [
                'json' => ['title' => ''],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'The title cannot be blank'],
            ],
        ]);
    }

    public function testCreateCategoryWithTitleTooShortAsAdmin(): void
    {
        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-short-title@example.com',
            password: 'adminpassword',
        );

        $this->requestUnsafe(
            $client,
            'POST',
            '/categories',
            $csrfToken,
            [
                'json' => ['title' => 'A'],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title'],
            ],
        ]);
    }

    public function testCreateCategoryWithDuplicateTitleAsAdmin(): void
    {
        CategoryFactory::createOne(['title' => 'Sales']);

        [$client, $csrfToken, $admin] = $this->createAuthenticatedAdmin(
            email: 'admin-duplicate-title@example.com',
            password: 'adminpassword',
        );

        $this->requestUnsafe(
            $client,
            'POST',
            '/categories',
            $csrfToken,
            [
                'json' => ['title' => 'Sales'],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
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

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-filter-title@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories?title=Development');

        self::assertResponseIsSuccessful();
        $data = $response->toArray();

        self::assertCount(1, $data['member']);
        self::assertEquals('Development', $data['member'][0]['title']);
    }

    public function testFilterCategoriesByTitlePartial(): void
    {
        CategoryFactory::createOne(['title' => 'Web Development']);
        CategoryFactory::createOne(['title' => 'Mobile Development']);
        CategoryFactory::createOne(['title' => 'Design']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-filter-partial@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories?title=Development');

        self::assertResponseIsSuccessful();
        $data = $response->toArray();

        self::assertEquals(2, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        self::assertContains('Web Development', $titles);
        self::assertContains('Mobile Development', $titles);
    }

    public function testOrderCategoriesByTitle(): void
    {
        CategoryFactory::createOne(['title' => 'Zebra']);
        CategoryFactory::createOne(['title' => 'Apple']);
        CategoryFactory::createOne(['title' => 'Mango']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-order-asc@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories?order[title]=asc');

        self::assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        self::assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }

    public function testOrderCategoriesByTitleDesc(): void
    {
        CategoryFactory::createOne(['title' => 'Zebra']);
        CategoryFactory::createOne(['title' => 'Apple']);
        CategoryFactory::createOne(['title' => 'Mango']);

        [$client, $csrfToken, $user] = $this->createAuthenticatedUser(
            email: 'user-order-desc@example.com',
            password: 'password',
        );

        $response = $client->request('GET', '/categories?order[title]=desc');

        self::assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        self::assertEquals(['Zebra', 'Mango', 'Apple'], $titles);
    }
}

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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-categories'),
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

        [$client] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-categories'),
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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-get-category'),
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

        [$client] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-get-category'),
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
        [$client, $csrfToken] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-create-category'),
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
        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-create-category'),
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

        [$client, $csrfToken] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-update-category'),
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

        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-update-category'),
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

        [$client, $csrfToken] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-delete-category'),
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

        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-delete-category'),
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
        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-blank-title'),
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
        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-short-title'),
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

        [$client, $csrfToken] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin-duplicate-title'),
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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-filter-title'),
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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-filter-partial'),
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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-order-asc'),
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

        [$client] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user-order-desc'),
            password: 'password',
        );

        $response = $client->request('GET', '/categories?order[title]=desc');

        self::assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        self::assertEquals(['Zebra', 'Mango', 'Apple'], $titles);
    }
}

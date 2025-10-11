<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class SkillsTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private static string $token;
    private static ?int $categoryId = null;
    private static bool $initialized = false;
    private static int $counter = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$initialized) {
            self::$token = $this->createAuthenticatedUser('skill-test@example.com');
            self::$categoryId = $this->createCategory('Test Category');
            self::$initialized = true;
        }

        self::$counter++;
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$initialized = false;
        self::$categoryId = null;
        self::$counter = 0;
    }

    private function createCategory(string $title): int
    {
        $uniqueTitle = self::$counter . '_' . $title;

        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => self::$token,
            'json' => ['title' => $uniqueTitle],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray()['id'];
    }

    private function createSkill(string $title, int $categoryId): array
    {
        $uniqueTitle = self::$counter . '_' . $title;

        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $uniqueTitle,
                'category' => '/categories/' . $categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray();
    }

    // ==================== CRUD Operations ====================

    public function testGetSkills(): void
    {
        $skill1 = $this->createSkill('PHP', self::$categoryId);
        $skill2 = $this->createSkill('JavaScript', self::$categoryId);
        $skill3 = $this->createSkill('Python', self::$categoryId);

        $response = static::createClient()->request('GET', '/skills', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertContains($skill1['title'], $titles);
        $this->assertContains($skill2['title'], $titles);
        $this->assertContains($skill3['title'], $titles);
    }

    public function testCreateSkill(): void
    {
        $uniqueTitle = self::$counter . '_React';

        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $uniqueTitle,
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => $uniqueTitle,
        ]);
    }

    public function testGetSkill(): void
    {
        $skill = $this->createSkill('Vue.js', self::$categoryId);

        $response = static::createClient()->request('GET', "/skills/{$skill['id']}", [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => $skill['title']]);
    }

    public function testUpdateSkill(): void
    {
        $skill = $this->createSkill('Angular', self::$categoryId);
        $newTitle = self::$counter . '_AngularJS';

        static::createClient()->request('PATCH', "/skills/{$skill['id']}", [
            'auth_bearer' => self::$token,
            'json' => ['title' => $newTitle],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => $newTitle]);
    }

    public function testDeleteSkill(): void
    {
        $skill = $this->createSkill('jQuery', self::$categoryId);

        static::createClient()->request('DELETE', "/skills/{$skill['id']}", [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    // ==================== Validations ====================

    public function testCreateSkillWithBlankTitle(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => '',
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'The title cannot be blank'],
            ],
        ]);
    }

    public function testCreateSkillWithTitleTooShort(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => 'C',
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title'],
            ],
        ]);
    }

    public function testCreateSkillWithoutCategory(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => self::$counter . '_Rust',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'category', 'message' => 'The category cannot be null'],
            ],
        ]);
    }

    public function testCreateSkillWithDuplicateTitleInSameCategory(): void
    {
        $title = self::$counter . '_Node.js';

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $title,
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $title,
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'This skill already exists in this category'],
            ],
        ]);
    }

    public function testCreateSkillWithSameTitleInDifferentCategory(): void
    {
        $category2Id = $this->createCategory('Another Category');
        $title = self::$counter . '_Docker';

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $title,
                'category' => '/categories/' . self::$categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => self::$token,
            'json' => [
                'title' => $title,
                'category' => '/categories/' . $category2Id,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => $title,
        ]);
    }

    // ==================== Filters ====================

    public function testFilterSkillsByCategory(): void
    {
        $category2Id = $this->createCategory('Design');

        $skill1 = $this->createSkill('TypeScript', self::$categoryId);
        $skill2 = $this->createSkill('Go', self::$categoryId);
        $skill3 = $this->createSkill('Photoshop', $category2Id);

        $response = static::createClient()->request('GET', '/skills?category=' . self::$categoryId, [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');
        $this->assertContains($skill1['title'], $titles);
        $this->assertContains($skill2['title'], $titles);
        $this->assertNotContains($skill3['title'], $titles);
    }

    public function testFilterSkillsByTitlePartial(): void
    {
        $skill1 = $this->createSkill('JavaScript', self::$categoryId);
        $skill2 = $this->createSkill('TypeScript', self::$categoryId);
        $this->createSkill('Python', self::$categoryId);

        // ✅ Filtrer avec le préfixe unique du test
        $searchTerm = self::$counter . '_';
        $response = static::createClient()->request('GET', '/skills?title=' . $searchTerm, [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // ✅ On doit avoir au moins 3 skills de ce test
        $this->assertGreaterThanOrEqual(3, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        $this->assertContains($skill1['title'], $titles);
        $this->assertContains($skill2['title'], $titles);
    }

    public function testOrderSkillsByTitle(): void
    {
        $skill1 = $this->createSkill('Zebra', self::$categoryId);
        $skill2 = $this->createSkill('Apple', self::$categoryId);
        $skill3 = $this->createSkill('Mango', self::$categoryId);

        // ✅ Filtrer uniquement les skills de ce test
        $response = static::createClient()->request('GET', '/skills?title=' . self::$counter . '_&order[title]=asc', [
            'auth_bearer' => self::$token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        // ✅ Vérifier l'ordre alphabétique
        $this->assertEquals([
            $skill2['title'], // Apple
            $skill3['title'], // Mango
            $skill1['title'], // Zebra
        ], $titles);
    }
}

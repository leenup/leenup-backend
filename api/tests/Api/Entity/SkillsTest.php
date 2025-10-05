<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Category;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;

class SkillsTest extends ApiTestCase
{
    use AuthenticatedApiTestTrait;

    private string $token;
    private ?int $categoryId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->createAuthenticatedUser('skill-test@example.com');
        $this->categoryId = $this->createCategory('Test Category');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Skill')->execute();
        $em->createQuery('DELETE FROM App\Entity\Category')->execute();
    }

    private function createCategory(string $title): int
    {
        $response = static::createClient()->request('POST', '/categories', [
            'auth_bearer' => $this->token,
            'json' => ['title' => $title],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray()['id'];
    }

    private function createSkill(string $title, int $categoryId): array
    {
        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => $title,
                'category' => '/categories/' . $categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        return $response->toArray();
    }

    // ==================== CRUD Operations ====================

    public function testGetSkills(): void
    {
        $this->createSkill('PHP', $this->categoryId);
        $this->createSkill('JavaScript', $this->categoryId);
        $this->createSkill('Python', $this->categoryId);

        $response = static::createClient()->request('GET', '/skills', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Collection',
            'totalItems' => 3,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertContains('PHP', $titles);
        $this->assertContains('JavaScript', $titles);
        $this->assertContains('Python', $titles);
    }

    public function testCreateSkill(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'React',
                'category' => '/categories/' . $this->categoryId,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => 'React',
        ]);
    }

    public function testGetSkill(): void
    {
        $skill = $this->createSkill('Vue.js', $this->categoryId);

        static::createClient()->request('GET', "/skills/{$skill['id']}", [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'Vue.js']);
    }

    public function testUpdateSkill(): void
    {
        $skill = $this->createSkill('Angular', $this->categoryId);

        static::createClient()->request('PATCH', "/skills/{$skill['id']}", [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'AngularJS'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'AngularJS']);
    }

    public function testDeleteSkill(): void
    {
        $skill = $this->createSkill('jQuery', $this->categoryId);

        static::createClient()->request('DELETE', "/skills/{$skill['id']}", [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    // ==================== Validations ====================

    public function testCreateSkillWithBlankTitle(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => '',
                'category' => '/categories/' . $this->categoryId,
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
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'C',
                'category' => '/categories/' . $this->categoryId,
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
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Rust',
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
        $this->createSkill('Node.js', $this->categoryId);

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Node.js',
                'category' => '/categories/' . $this->categoryId,
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

        $this->createSkill('Docker', $this->categoryId);

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Docker',
                'category' => '/categories/' . $category2Id,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => 'Docker',
        ]);
    }

    // ==================== Filters ====================

    public function testFilterSkillsByCategory(): void
    {
        $category2Id = $this->createCategory('Design');

        $this->createSkill('TypeScript', $this->categoryId);
        $this->createSkill('Go', $this->categoryId);
        $this->createSkill('Photoshop', $category2Id);

        $response = static::createClient()->request('GET', '/skills?category=' . $this->categoryId, [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(2, $data['member']);
        $titles = array_column($data['member'], 'title');
        $this->assertContains('TypeScript', $titles);
        $this->assertContains('Go', $titles);
        $this->assertNotContains('Photoshop', $titles);
    }

    public function testFilterSkillsByTitlePartial(): void
    {
        $this->createSkill('JavaScript', $this->categoryId);
        $this->createSkill('TypeScript', $this->categoryId);
        $this->createSkill('Python', $this->categoryId);

        $response = static::createClient()->request('GET', '/skills?title=Script', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(2, $data['member']);
        $titles = array_column($data['member'], 'title');
        $this->assertContains('JavaScript', $titles);
        $this->assertContains('TypeScript', $titles);
    }

    public function testOrderSkillsByTitle(): void
    {
        $this->createSkill('Rust', $this->categoryId);
        $this->createSkill('Go', $this->categoryId);
        $this->createSkill('C++', $this->categoryId);

        $response = static::createClient()->request('GET', '/skills?order[title]=asc', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertEquals(['C++', 'Go', 'Rust'], $titles);
    }
}

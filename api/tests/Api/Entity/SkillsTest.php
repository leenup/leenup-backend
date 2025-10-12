<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class SkillsTest extends ApiTestCase
{
    use Factories;

    private string $token;
    private $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur authentifié pour chaque test
        UserFactory::createOne([
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

        // Créer une catégorie de test
        $this->category = CategoryFactory::createOne(['title' => 'Test Category']);
    }

    // ==================== CRUD Operations ====================

    public function testGetSkills(): void
    {
        SkillFactory::createOne(['title' => 'PHP', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'JavaScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Python', 'category' => $this->category]);

        $response = static::createClient()->request('GET', '/skills', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Skill',
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
        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'React',
                'category' => '/categories/' . $this->category->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/contexts/Skill',
            '@type' => 'Skill',
            'title' => 'React',
        ]);
        $this->assertMatchesRegularExpression('~^/skills/\d+$~', $response->toArray()['@id']);
    }

    public function testGetSkill(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $this->category]);

        $response = static::createClient()->request('GET', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => 'Vue.js',
        ]);
    }

    public function testUpdateSkill(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Angular', 'category' => $this->category]);

        static::createClient()->request('PATCH', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'AngularJS'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'AngularJS']);
    }

    public function testDeleteSkill(): void
    {
        $skill = SkillFactory::createOne(['title' => 'jQuery', 'category' => $this->category]);

        static::createClient()->request('DELETE', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que la skill n'existe plus
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(\App\Entity\Skill::class)->findOneBy(['title' => 'jQuery'])
        );
    }

    // ==================== Validations ====================

    public function testCreateSkillWithBlankTitle(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => '',
                'category' => '/categories/' . $this->category->getId(),
            ],
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

    public function testCreateSkillWithTitleTooShort(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'C',
                'category' => '/categories/' . $this->category->getId(),
            ],
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
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'category', 'message' => 'The category cannot be null'],
            ],
        ]);
    }

    public function testCreateSkillWithDuplicateTitleInSameCategory(): void
    {
        SkillFactory::createOne(['title' => 'Node.js', 'category' => $this->category]);

        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Node.js',
                'category' => '/categories/' . $this->category->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                ['propertyPath' => 'title', 'message' => 'This skill already exists in this category'],
            ],
        ]);
    }

    public function testCreateSkillWithSameTitleInDifferentCategory(): void
    {
        $category2 = CategoryFactory::createOne(['title' => 'Another Category']);

        SkillFactory::createOne(['title' => 'Docker', 'category' => $this->category]);

        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'Docker',
                'category' => '/categories/' . $category2->getId(),
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
        $category2 = CategoryFactory::createOne(['title' => 'Design']);

        SkillFactory::createOne(['title' => 'TypeScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Go', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Photoshop', 'category' => $category2]);

        $response = static::createClient()->request('GET', '/skills?category=' . $this->category->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        $this->assertContains('TypeScript', $titles);
        $this->assertContains('Go', $titles);
        $this->assertNotContains('Photoshop', $titles);
    }

    public function testFilterSkillsByTitlePartial(): void
    {
        SkillFactory::createOne(['title' => 'JavaScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'TypeScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Python', 'category' => $this->category]);

        $response = static::createClient()->request('GET', '/skills?title=Script', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);

        $titles = array_column($data['member'], 'title');
        $this->assertContains('JavaScript', $titles);
        $this->assertContains('TypeScript', $titles);
    }

    public function testOrderSkillsByTitle(): void
    {
        SkillFactory::createOne(['title' => 'Zebra', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Apple', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Mango', 'category' => $this->category]);

        $response = static::createClient()->request('GET', '/skills?order[title]=asc', [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');

        // Vérifier l'ordre alphabétique
        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }
}

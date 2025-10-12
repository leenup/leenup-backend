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
    private string $adminToken;
    private $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Pour les tests User
        $user = UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        // Pour les tests Admin
        $adminUser = UserFactory::createOne([
            'email' => 'admin@exemple.com',
            'plainPassword' => 'adminpassword',
            'roles' => ['ROLE_ADMIN'],
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => [
                'email' => 'test@example.com',
                'password' => 'password',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $adminResponse = static::createClient()->request('POST', '/auth', [
            'json' => [
                'email' => 'admin@exemple.com',
                'password' => 'adminpassword',
            ],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->token = $response->toArray()['token'];
        $this->adminToken = $adminResponse->toArray()['token'];

        $this->category = CategoryFactory::createOne(['title' => 'Test Category']);
    }

    // ==================== GET Operations ====================

    public function testGetSkillsAsUser(): void
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

    public function testGetSkillsAsAdmin(): void
    {
        SkillFactory::createOne(['title' => 'Ruby', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Java', 'category' => $this->category]);

        $response = static::createClient()->request('GET', '/skills', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Skill',
            '@type' => 'Collection',
            'totalItems' => 2,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertContains('Ruby', $titles);
        $this->assertContains('Java', $titles);
    }

    public function testGetSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $this->category]);

        static::createClient()->request('GET', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => 'Vue.js',
        ]);
    }

    public function testGetSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $this->category]);

        static::createClient()->request('GET', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'Skill',
            'title' => 'Vue.js',
        ]);
    }

    // ==================== POST Operations ====================

    public function testCreateSkillAsUser(): void
    {
        static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->token,
            'json' => [
                'title' => 'React',
                'category' => '/categories/' . $this->category->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testCreateSkillAsAdmin(): void
    {
        $response = static::createClient()->request('POST', '/skills', [
            'auth_bearer' => $this->adminToken,
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

    // ==================== PATCH Operations ====================

    public function testUpdateSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Angular', 'category' => $this->category]);

        static::createClient()->request('PATCH', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
            'json' => ['title' => 'AngularJS'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testUpdateSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Angular', 'category' => $this->category]);

        static::createClient()->request('PATCH', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->adminToken,
            'json' => ['title' => 'AngularJS'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['title' => 'AngularJS']);
    }

    // ==================== DELETE Operations ====================

    public function testDeleteSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'jQuery', 'category' => $this->category]);

        static::createClient()->request('DELETE', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->token,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only admins can access this resource.',
        ]);
    }

    public function testDeleteSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'jQuery', 'category' => $this->category]);

        static::createClient()->request('DELETE', '/skills/' . $skill->getId(), [
            'auth_bearer' => $this->adminToken,
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
            'auth_bearer' => $this->adminToken,
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
            'auth_bearer' => $this->adminToken,
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
            'auth_bearer' => $this->adminToken,
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
            'auth_bearer' => $this->adminToken,
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
            'auth_bearer' => $this->adminToken,
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

        $this->assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }
}

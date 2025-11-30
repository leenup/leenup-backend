<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Skill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class SkillsTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $userClient;
    private HttpClientInterface $adminClient;

    private string $userCsrfToken;
    private string $adminCsrfToken;

    private $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Utilisateur simple
        [
            $this->userClient,
            $this->userCsrfToken,
            $user,
        ] = $this->createAuthenticatedUser(
            email: 'test@example.com',
            password: 'password',
        );

        // Admin
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $adminUser,
        ] = $this->createAuthenticatedAdmin(
            email: 'admin@exemple.com',
            password: 'adminpassword',
        );

        $this->category = CategoryFactory::createOne(['title' => 'Test Category']);
    }

    // ==================== GET Operations ====================

    public function testGetSkillsAsUser(): void
    {
        SkillFactory::createOne(['title' => 'PHP', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'JavaScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Python', 'category' => $this->category]);

        $response = $this->userClient->request('GET', '/skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/Skill', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertSame(3, $data['totalItems'] ?? null);

        $titles = array_column($data['member'], 'title');

        self::assertContains('PHP', $titles);
        self::assertContains('JavaScript', $titles);
        self::assertContains('Python', $titles);
    }

    public function testGetSkillsAsAdmin(): void
    {
        SkillFactory::createOne(['title' => 'Ruby', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Java', 'category' => $this->category]);

        $response = $this->adminClient->request('GET', '/skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/Skill', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertSame(2, $data['totalItems'] ?? null);

        $titles = array_column($data['member'], 'title');

        self::assertContains('Ruby', $titles);
        self::assertContains('Java', $titles);
    }

    public function testGetSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $this->category]);

        $response = $this->userClient->request('GET', '/skills/'.$skill->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Skill', $data['@type'] ?? null);
        self::assertSame('Vue.js', $data['title'] ?? null);
    }

    public function testGetSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $this->category]);

        $response = $this->adminClient->request('GET', '/skills/'.$skill->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Skill', $data['@type'] ?? null);
        self::assertSame('Vue.js', $data['title'] ?? null);
    }

    // ==================== POST Operations ====================

    public function testCreateSkillAsUser(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'title' => 'React',
                    'category' => '/categories/'.$this->category->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame('Only admins can access this resource.', $data['detail'] ?? null);
    }

    public function testCreateSkillAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => 'React',
                    'category' => '/categories/'.$this->category->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        self::assertSame('/contexts/Skill', $data['@context'] ?? null);
        self::assertSame('Skill', $data['@type'] ?? null);
        self::assertSame('React', $data['title'] ?? null);
        self::assertMatchesRegularExpression('~^/skills/\d+$~', $data['@id'] ?? '');
    }

    // ==================== PATCH Operations ====================

    public function testUpdateSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Angular', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->userClient,
            'PATCH',
            '/skills/'.$skill->getId(),
            $this->userCsrfToken,
            [
                'json' => ['title' => 'AngularJS'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame('Only admins can access this resource.', $data['detail'] ?? null);
    }

    public function testUpdateSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'Angular', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->adminClient,
            'PATCH',
            '/skills/'.$skill->getId(),
            $this->adminCsrfToken,
            [
                'json' => ['title' => 'AngularJS'],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('AngularJS', $data['title'] ?? null);
    }

    // ==================== DELETE Operations ====================

    public function testDeleteSkillAsUser(): void
    {
        $skill = SkillFactory::createOne(['title' => 'jQuery', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/skills/'.$skill->getId(),
            $this->userCsrfToken
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame('Only admins can access this resource.', $data['detail'] ?? null);
    }

    public function testDeleteSkillAsAdmin(): void
    {
        $skill = SkillFactory::createOne(['title' => 'jQuery', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->adminClient,
            'DELETE',
            '/skills/'.$skill->getId(),
            $this->adminCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $repo = static::getContainer()->get('doctrine')->getRepository(Skill::class);
        self::assertNull($repo->findOneBy(['title' => 'jQuery']));
    }

    // ==================== Validations ====================

    public function testCreateSkillWithBlankTitle(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => '',
                    'category' => '/categories/'.$this->category->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $found = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'title'
                && ($violation['message'] ?? null) === 'The title cannot be blank') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Expected violation on "title" with message "The title cannot be blank".');
    }

    public function testCreateSkillWithTitleTooShort(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => 'C',
                    'category' => '/categories/'.$this->category->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $hasTitleViolation = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'title') {
                $hasTitleViolation = true;
                break;
            }
        }

        self::assertTrue($hasTitleViolation, 'Expected at least one violation on "title".');
    }

    public function testCreateSkillWithoutCategory(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => 'Rust',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $found = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'category'
                && ($violation['message'] ?? null) === 'The category cannot be null') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Expected violation on "category" with message "The category cannot be null".');
    }

    public function testCreateSkillWithDuplicateTitleInSameCategory(): void
    {
        SkillFactory::createOne(['title' => 'Node.js', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => 'Node.js',
                    'category' => '/categories/'.$this->category->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $found = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'title'
                && ($violation['message'] ?? null) === 'This skill already exists in this category') {
                $found = true;
                break;
            }
        }

        self::assertTrue(
            $found,
            'Expected violation on "title" with message "This skill already exists in this category".'
        );
    }

    public function testCreateSkillWithSameTitleInDifferentCategory(): void
    {
        $category2 = CategoryFactory::createOne(['title' => 'Another Category']);

        SkillFactory::createOne(['title' => 'Docker', 'category' => $this->category]);

        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'title' => 'Docker',
                    'category' => '/categories/'.$category2->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Skill', $data['@type'] ?? null);
        self::assertSame('Docker', $data['title'] ?? null);
    }

    // ==================== Filters ====================

    public function testFilterSkillsByCategory(): void
    {
        $category2 = CategoryFactory::createOne(['title' => 'Design']);

        SkillFactory::createOne(['title' => 'TypeScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Go', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Photoshop', 'category' => $category2]);

        $response = $this->userClient->request(
            'GET',
            '/skills?category='.$this->category->getId()
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems'] ?? null);

        $titles = array_column($data['member'], 'title');
        self::assertContains('TypeScript', $titles);
        self::assertContains('Go', $titles);
        self::assertNotContains('Photoshop', $titles);
    }

    public function testFilterSkillsByTitlePartial(): void
    {
        SkillFactory::createOne(['title' => 'JavaScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'TypeScript', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Python', 'category' => $this->category]);

        $response = $this->userClient->request(
            'GET',
            '/skills?title=Script'
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems'] ?? null);

        $titles = array_column($data['member'], 'title');
        self::assertContains('JavaScript', $titles);
        self::assertContains('TypeScript', $titles);
    }

    public function testOrderSkillsByTitle(): void
    {
        SkillFactory::createOne(['title' => 'Zebra', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Apple', 'category' => $this->category]);
        SkillFactory::createOne(['title' => 'Mango', 'category' => $this->category]);

        $response = $this->userClient->request(
            'GET',
            '/skills?order[title]=asc'
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();

        $titles = array_column($data['member'], 'title');
        self::assertEquals(['Apple', 'Mango', 'Zebra'], $titles);
    }
}

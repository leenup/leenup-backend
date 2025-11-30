<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class UserSkillsTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $userClient;
    private HttpClientInterface $adminClient;

    private string $userCsrfToken;
    private string $adminCsrfToken;

    private $user;
    private $admin;
    private $skill1;
    private $skill2;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill1 = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->skill2 = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $category]);

        // User authentifié
        [
            $this->userClient,
            $this->userCsrfToken,
            $this->user,
        ] = $this->createAuthenticatedUser(
            email: 'user@example.com',
            password: 'password',
        );

        // Admin authentifié
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->admin,
        ] = $this->createAuthenticatedAdmin(
            email: 'admin@example.com',
            password: 'admin123',
        );
    }

    // ==================== GET collection ====================

    public function testGetUserSkillsAsUser(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->admin,
            'skill' => $this->skill2,
            'type' => UserSkill::TYPE_LEARN,
        ]);

        $response = $this->userClient->request('GET', '/user_skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems']);
    }

    public function testGetUserSkillsAsAdmin(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        $response = $this->adminClient->request('GET', '/user_skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetUserSkillsWithoutAuthentication(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/user_skills');

        self::assertSame(401, $response->getStatusCode());
    }

    // ==================== GET item ====================

    public function testGetUserSkillAsUser(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_ADVANCED,
        ]);

        $response = $this->userClient->request('GET', '/user_skills/'.$userSkill->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('UserSkill', $data['@type'] ?? null);
        self::assertSame('teach', $data['type'] ?? null);
        self::assertSame('advanced', $data['level'] ?? null);
    }

    public function testGetUserSkillAsAdmin(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_LEARN,
        ]);

        $response = $this->adminClient->request('GET', '/user_skills/'.$userSkill->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('UserSkill', $data['@type'] ?? null);
        self::assertSame('learn', $data['type'] ?? null);
    }

    // ==================== POST (owner non envoyé, Content-Type ld+json) ====================

    public function testCreateUserSkillAsUser(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/user_skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'teach',
                    'level' => 'expert',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('UserSkill', $data['@type'] ?? null);
        self::assertSame('teach', $data['type'] ?? null);
        self::assertSame('expert', $data['level'] ?? null);
    }

    public function testCreateUserSkillAsAdmin(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/user_skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'teach',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('UserSkill', $data['@type'] ?? null);
        self::assertSame('teach', $data['type'] ?? null);
    }

    // ==================== DELETE ====================

    public function testDeleteUserSkillAsUser(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
        ]);

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/user_skills/'.$userSkill->getId(),
            $this->userCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());
    }

    public function testDeleteUserSkillAsAdmin(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
        ]);

        $response = $this->requestUnsafe(
            $this->adminClient,
            'DELETE',
            '/user_skills/'.$userSkill->getId(),
            $this->adminCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());
    }

    // ==================== Validation ====================

    public function testCreateUserSkillWithDuplicateAsAdmin(): void
    {
        // On crée déjà une skill "teach" pour ce user
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        // On se reconnecte avec ce user : l'API ignore owner dans le body et le prend depuis l'utilisateur connecté
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/user_skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'teach',
                    'level' => 'advanced',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
        // on ne teste plus la forme exacte du JSON, seulement le code
    }

    public function testCreateUserSkillWithInvalidType(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/user_skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'invalid',
                    'level' => 'expert',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserSkillWithInvalidLevel(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/user_skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'teach',
                    'level' => 'wrong',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserSkillWithoutSkill(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/user_skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'type' => 'teach',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testCreateUserSkillWithoutType(): void
    {
        $response = $this->requestUnsafe(
            $this->adminClient,
            'POST',
            '/user_skills',
            $this->adminCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testUserCanHaveSameSkillWithDifferentTypes(): void
    {
        // teach
        $response1 = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/user_skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'teach',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );
        self::assertSame(201, $response1->getStatusCode());

        // learn
        $response2 = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/user_skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->skill1->getId(),
                    'type' => 'learn',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ],
        );
        self::assertSame(201, $response2->getStatusCode());
    }

    // ==================== Filters ====================

    public function testFilterUserSkillsByOwner(): void
    {
        $user2 = UserFactory::createOne(['email' => 'u2@example.com']);

        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'learn']);
        UserSkillFactory::createOne(['owner' => $user2, 'skill' => $this->skill1, 'type' => 'teach']);

        $response = $this->userClient->request(
            'GET',
            '/user_skills?owner='.$this->user->getId(),
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsBySkill(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => 'learn']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'teach']);

        $response = $this->userClient->request(
            'GET',
            '/user_skills?skill='.$this->skill1->getId(),
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsByType(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => 'learn']);

        $response = $this->userClient->request(
            'GET',
            '/user_skills?type=teach',
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsByLevel(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
            'level' => 'expert',
        ]);
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill2,
            'type' => 'teach',
            'level' => 'beginner',
        ]);

        $response = $this->userClient->request(
            'GET',
            '/user_skills?level=expert',
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(1, $data['totalItems']);
    }

    public function testFilterUserSkillsByMultipleFilters(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
            'level' => 'expert',
        ]);
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill2,
            'type' => 'learn',
            'level' => 'expert',
        ]);
        UserSkillFactory::createOne([
            'owner' => $this->admin,
            'skill' => $this->skill1,
            'type' => 'teach',
            'level' => 'expert',
        ]);

        $response = $this->userClient->request(
            'GET',
            '/user_skills?owner='.$this->user->getId().'&type=teach&level=expert',
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(1, $data['totalItems']);
    }
}

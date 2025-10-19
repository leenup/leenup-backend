<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class UserSkillsTest extends ApiTestCase
{
    use Factories;

    private string $userToken;
    private string $adminToken;
    private $user;
    private $admin;
    private $skill1;
    private $skill2;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une catégorie et des skills
        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill1 = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->skill2 = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $category]);

        // User normal
        $this->user = UserFactory::createOne([
            'email' => 'user@example.com',
            'plainPassword' => 'password',
        ]);

        // Admin
        $this->admin = UserFactory::createOne([
            'email' => 'admin@example.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

        // Tokens
        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->userToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'admin@example.com', 'password' => 'admin123'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->adminToken = $response->toArray()['token'];
    }

    // ==================== GET /user_skills (Collection) ====================

    public function testGetUserSkillsAsUser(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->admin,
            'skill' => $this->skill2,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);

        $response = static::createClient()->request('GET', '/user_skills', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/UserSkill', $data['@context']);
        $this->assertEquals(2, $data['totalItems']);
    }

    public function testGetUserSkillsAsAdmin(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        $response = static::createClient()->request('GET', '/user_skills', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetUserSkillsWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/user_skills');
        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== GET /user_skills/{id} (Item) ====================

    public function testGetUserSkillAsUser(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_ADVANCED,
        ]);

        static::createClient()->request('GET', '/user_skills/' . $userSkill->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'UserSkill',
            'type' => 'teach',
            'level' => 'advanced',
        ]);
    }

    public function testGetUserSkillAsAdmin(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);

        static::createClient()->request('GET', '/user_skills/' . $userSkill->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'UserSkill',
            'type' => 'learn',
            'level' => 'beginner',
        ]);
    }

    // ==================== POST /user_skills ====================

    public function testCreateUserSkillAsUser(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only admins can create user skills directly.',
        ]);
    }

    public function testCreateUserSkillAsAdmin(): void
    {
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'UserSkill',
            'type' => 'teach',
            'level' => 'expert',
        ]);
    }

    // ==================== DELETE /user_skills/{id} ====================

    public function testDeleteUserSkillAsUser(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        static::createClient()->request('DELETE', '/user_skills/' . $userSkill->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only admins can delete user skills directly.',
        ]);
    }

    public function testDeleteUserSkillAsAdmin(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        static::createClient()->request('DELETE', '/user_skills/' . $userSkill->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        // Vérifier que le UserSkill n'existe plus
        $this->assertNull(
            static::getContainer()->get('doctrine')->getRepository(UserSkill::class)->find($userSkill->getId())
        );
    }

    // ==================== Validations ====================

    public function testCreateUserSkillWithDuplicateAsAdmin(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'advanced',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'owner',
                'message' => 'This user already has this skill with this type',
            ]],
        ]);
    }

    public function testCreateUserSkillWithInvalidType(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'invalid_type',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'type',
            ]],
        ]);
    }

    public function testCreateUserSkillWithInvalidLevel(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'invalid_level',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'level',
            ]],
        ]);
    }

    public function testCreateUserSkillWithoutOwner(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'owner',
                'message' => 'The user cannot be null',
            ]],
        ]);
    }

    public function testCreateUserSkillWithoutSkill(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'type' => 'teach',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'skill',
                'message' => 'The skill cannot be null',
            ]],
        ]);
    }

    public function testCreateUserSkillWithoutType(): void
    {
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'type',
                'message' => 'The type cannot be blank',
            ]],
        ]);
    }

    public function testUserCanHaveSameSkillWithDifferentTypes(): void
    {
        // Créer teach
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'teach',
                'level' => 'expert',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // Créer learn pour la même skill
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'owner' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill1->getId(),
                'type' => 'learn',
                'level' => 'beginner',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    // ==================== Filters ====================

    public function testFilterUserSkillsByOwner(): void
    {
        $user2 = UserFactory::createOne(['email' => 'user2@example.com']);

        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH]);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => UserSkill::TYPE_LEARN]);
        UserSkillFactory::createOne(['owner' => $user2, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH]);

        $response = static::createClient()->request('GET', '/user_skills?owner=' . $this->user->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsBySkill(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH]);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_LEARN]);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => UserSkill::TYPE_TEACH]);

        $response = static::createClient()->request('GET', '/user_skills?skill=' . $this->skill1->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsByType(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH]);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => UserSkill::TYPE_TEACH]);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_LEARN]);

        $response = static::createClient()->request('GET', '/user_skills?type=teach', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(2, $data['totalItems']);
    }

    public function testFilterUserSkillsByLevel(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH, 'level' => UserSkill::LEVEL_EXPERT]);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => UserSkill::TYPE_TEACH, 'level' => UserSkill::LEVEL_BEGINNER]);

        $response = static::createClient()->request('GET', '/user_skills?level=expert', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(1, $data['totalItems']);
    }

    public function testFilterUserSkillsByMultipleFilters(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH, 'level' => UserSkill::LEVEL_EXPERT]);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => UserSkill::TYPE_LEARN, 'level' => UserSkill::LEVEL_EXPERT]);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => UserSkill::TYPE_TEACH, 'level' => UserSkill::LEVEL_EXPERT]);

        $response = static::createClient()->request('GET', '/user_skills?owner=' . $this->user->getId() . '&type=teach&level=expert', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(1, $data['totalItems']);
    }
}

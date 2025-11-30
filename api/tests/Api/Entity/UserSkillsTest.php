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

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill1 = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->skill2 = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $category]);

        $this->user = UserFactory::createOne([
            'email' => 'user@example.com',
            'plainPassword' => 'password',
        ]);

        $this->admin = UserFactory::createOne([
            'email' => 'admin@example.com',
            'plainPassword' => 'admin123',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
        ]);

        // Tokens auth (controller custom, donc application/json OK ici)
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

        $response = static::createClient()->request('GET', '/user_skills', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    public function testGetUserSkillsAsAdmin(): void
    {
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        $response = static::createClient()->request('GET', '/user_skills', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(1, $response->toArray()['totalItems']);
    }

    public function testGetUserSkillsWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/user_skills');
        $this->assertResponseStatusCodeSame(401);
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

        static::createClient()->request('GET', '/user_skills/'.$userSkill->getId(), [
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
        ]);

        static::createClient()->request('GET', '/user_skills/'.$userSkill->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'UserSkill',
            'type' => 'learn',
        ]);
    }

    // ==================== POST (owner non envoyé, Content-Type ld+json) ====================

    public function testCreateUserSkillAsUser(): void
    {
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
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

    public function testCreateUserSkillAsAdmin(): void
    {
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
                'type' => 'teach',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertJsonContains([
            '@type' => 'UserSkill',
            'type' => 'teach',
        ]);
    }

    // ==================== DELETE ====================

    public function testDeleteUserSkillAsUser(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
        ]);

        static::createClient()->request('DELETE', '/user_skills/'.$userSkill->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteUserSkillAsAdmin(): void
    {
        $userSkill = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => 'teach',
        ]);

        static::createClient()->request('DELETE', '/user_skills/'.$userSkill->getId(), [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseStatusCodeSame(204);
    }

    // ==================== Validation ====================

    public function testCreateUserSkillWithDuplicateAsAdmin(): void
    {
        // On crée déjà une skill "teach" pour le même user authentifié
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        // On se connecte avec CE user (et pas forcément l'admin)
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                // owner ignoré par l’API → pris depuis le token
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
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
                'type' => 'invalid',
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
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
                'type' => 'teach',
                'level' => 'wrong',
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

    public function testCreateUserSkillWithoutSkill(): void
    {
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'type' => 'teach',
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
        $response = static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->adminToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
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
        // teach
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
                'type' => 'teach',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(201);

        // learn
        static::createClient()->request('POST', '/user_skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/'.$this->skill1->getId(),
                'type' => 'learn',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        $this->assertResponseStatusCodeSame(201);
    }

    // ==================== Filters ====================

    public function testFilterUserSkillsByOwner(): void
    {
        $user2 = UserFactory::createOne(['email' => 'u2@example.com']);

        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'learn']);
        UserSkillFactory::createOne(['owner' => $user2, 'skill' => $this->skill1, 'type' => 'teach']);

        $response = static::createClient()->request(
            'GET',
            '/user_skills?owner='.$this->user->getId(),
            ['auth_bearer' => $this->userToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    public function testFilterUserSkillsBySkill(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => 'learn']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'teach']);

        $response = static::createClient()->request(
            'GET',
            '/user_skills?skill='.$this->skill1->getId(),
            ['auth_bearer' => $this->userToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    public function testFilterUserSkillsByType(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'teach']);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => 'learn']);

        $response = static::createClient()->request(
            'GET',
            '/user_skills?type=teach',
            ['auth_bearer' => $this->userToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals(2, $response->toArray()['totalItems']);
    }

    public function testFilterUserSkillsByLevel(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach', 'level' => 'expert']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'teach', 'level' => 'beginner']);

        $response = static::createClient()->request(
            'GET',
            '/user_skills?level=expert',
            ['auth_bearer' => $this->userToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, $response->toArray()['totalItems']);
    }

    public function testFilterUserSkillsByMultipleFilters(): void
    {
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill1, 'type' => 'teach', 'level' => 'expert']);
        UserSkillFactory::createOne(['owner' => $this->user, 'skill' => $this->skill2, 'type' => 'learn', 'level' => 'expert']);
        UserSkillFactory::createOne(['owner' => $this->admin, 'skill' => $this->skill1, 'type' => 'teach', 'level' => 'expert']);

        $response = static::createClient()->request(
            'GET',
            '/user_skills?owner='.$this->user->getId().'&type=teach&level=expert',
            ['auth_bearer' => $this->userToken]
        );

        $this->assertResponseIsSuccessful();
        $this->assertEquals(1, $response->toArray()['totalItems']);
    }
}

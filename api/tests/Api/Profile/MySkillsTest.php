<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Skill;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class MySkillsTest extends ApiTestCase
{
    use Factories;

    private User $user;
    private string $userToken;
    private User $anotherUser;
    private string $anotherUserToken;
    private Skill $reactSkill;
    private Skill $javascriptSkill;
    private Skill $angularSkill;
    private Skill $csharpSkill;
    private Skill $phpSkill;

    private UserSkill $userSkillReact;
    private UserSkill $userSkillJavaScript;
    private UserSkill $anotherUserSkillAngular;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);

        $this->reactSkill = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->javascriptSkill = SkillFactory::createOne(['title' => 'JavaScript', 'category' => $category]);
        $this->angularSkill = SkillFactory::createOne(['title' => 'Angular', 'category' => $category]);
        $this->csharpSkill = SkillFactory::createOne(['title' => 'C#', 'category' => $category]);
        $this->phpSkill = SkillFactory::createOne(['title' => 'PHP', 'category' => $category]);

        $this->user = UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
        ]);

        $this->userSkillReact = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->reactSkill,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);

        $this->userSkillJavaScript = UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->javascriptSkill,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->userToken = $response->toArray()['token'];

        $this->anotherUser = UserFactory::createOne([
            'email' => 'anotherUser@example.com',
            'plainPassword' => 'password',
        ]);

        $this->anotherUserSkillAngular = UserSkillFactory::createOne([
            'owner' => $this->anotherUser,
            'skill' => $this->angularSkill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->anotherUser,
            'skill' => $this->csharpSkill,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);

        $responseAnother = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'anotherUser@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->anotherUserToken = $responseAnother->toArray()['token'];
    }

    // ==================== GET /me/skills ====================

    public function testGetMySkills(): void
    {
        $response = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertSame('/contexts/MySkill', $data['@context']);
        $this->assertSame('Collection', $data['@type']);
        $this->assertArrayHasKey('member', $data);
        $this->assertArrayHasKey('totalItems', $data);
        $this->assertSame(2, $data['totalItems']);
        $this->assertCount(2, $data['member']);

        $skillIris = array_column($data['member'], 'skill');
        $reactSkillIri = '/skills/' . $this->reactSkill->getId();
        $javascriptSkillIri = '/skills/' . $this->javascriptSkill->getId();

        $actualSkillIris = array_column($skillIris, '@id');

        $this->assertContains($reactSkillIri, $actualSkillIris);
        $this->assertContains($javascriptSkillIri, $actualSkillIris);
    }

    public function testGetMySkillsWithoutAuth(): void
    {
        static::createClient()->request('GET', '/me/skills');
        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    // ==================== GET /me/skills/:id ====================

    public function testGetAnotherUsersSkillByIdFails(): void
    {
        $response = static::createClient()->request('GET', '/me/skills/' . $this->anotherUserSkillAngular->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'UserSkill not found',
        ]);
    }

    public function testGetMySkillById(): void
    {
        $userSkillId = $this->userSkillReact->getId();

        $response = static::createClient()->request('GET', '/me/skills/' . $userSkillId, [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame($userSkillId, $data['id']);
        $this->assertSame('learn', $data['type']);
        $this->assertSame('beginner', $data['level']);
    }

    // ==================== POST /me/skills ====================

    public function testPostNewMySkillSuccessfully(): void
    {
        $phpSkillIri = '/skills/' . $this->phpSkill->getId();
        $response = static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => $phpSkillIri,
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertSame(UserSkill::TYPE_TEACH, $data['type']);
    }

    public function testPostSameSkillWithDifferentTypeSuccessfully(): void
    {
        $reactSkillIri = '/skills/' . $this->reactSkill->getId();
        $response = static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => $reactSkillIri,
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $responseGet = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);
        $this->assertCount(3, $responseGet->toArray()['member'], 'After POST, the user should have 3 skills.');
    }

    public function testPostExistingMySkillFails(): void
    {
        $reactSkillIri = '/skills/' . $this->userSkillReact->getSkill()->getId();
        $response = static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => $reactSkillIri,
                'type' => UserSkill::TYPE_LEARN,
                'level' => UserSkill::LEVEL_INTERMEDIATE,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);

        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'skill',
                    'message' => 'You already have this skill with this type',
                ],
            ],
        ]);
    }

    public function testPostMySkillWithInvalidTypeFails(): void
    {
        $response = static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/' . $this->phpSkill->getId(),
                'type' => 'invalid_type',
                'level' => UserSkill::LEVEL_BEGINNER,
            ],
            'headers' => [
                'Content-Type' => 'application/ld+json',
                'Accept' => 'application/ld+json',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'type',
                    'message' => 'The type must be either "teach" or "learn"',
                ],
            ],
        ]);
    }

    // =================== DELETE /me/skills/:id ====================

    public function testDeleteMySkillById(): void
    {
        $userSkillIdToDelete = $this->userSkillJavaScript->getId();

        static::createClient()->request('DELETE', '/me/skills/' . $userSkillIdToDelete, [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(204);

        $responseGet = static::createClient()->request('GET', '/me/skills/' . $userSkillIdToDelete, [
            'auth_bearer' => $this->userToken,
        ]);
        $this->assertResponseStatusCodeSame(404, 'The skill should no longer be found after deletion.');

        $responseList = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);
        $this->assertCount(1, $responseList->toArray()['member'], 'Only one skill should remain in the list.');
    }

    public function testDeleteAnotherUsersSkillByIdFailsWith404(): void
    {
        $anotherUserSkillId = $this->anotherUserSkillAngular->getId();

        static::createClient()->request('DELETE', '/me/skills/' . $anotherUserSkillId, [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(404, 'An attempt to delete another user\'s skill must result in a 404.');

        $responseGet = static::createClient()->request('GET', '/me/skills/' . $anotherUserSkillId, [
            'auth_bearer' => $this->anotherUserToken,
        ]);
        $this->assertResponseIsSuccessful('The other user\'s skill should still exist.');
    }

    public function testDeleteMySkillByIdWithoutAuth(): void
    {
        static::createClient()->request('DELETE', '/me/skills/' . $this->userSkillJavaScript->getId());
        $this->assertResponseStatusCodeSame(401);
    }
}

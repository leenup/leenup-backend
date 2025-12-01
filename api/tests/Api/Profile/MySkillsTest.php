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

    public function testGetMySkillsResponseStructure(): void
    {
        $response = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // Vérifier la structure d'un item
        $firstSkill = $data['member'][0];
        $this->assertArrayHasKey('@id', $firstSkill);
        $this->assertArrayHasKey('@type', $firstSkill);
        $this->assertEquals('MySkill', $firstSkill['@type']);
        $this->assertArrayHasKey('id', $firstSkill);
        $this->assertArrayHasKey('skill', $firstSkill);
        $this->assertArrayHasKey('type', $firstSkill);
        $this->assertArrayHasKey('level', $firstSkill);
        $this->assertArrayHasKey('createdAt', $firstSkill);

        // Vérifier les types
        $this->assertIsInt($firstSkill['id']);
        $this->assertIsArray($firstSkill['skill']);
        $this->assertIsString($firstSkill['type']);
        $this->assertIsString($firstSkill['createdAt']);
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

    public function testGetMySkillsWhenEmpty(): void
    {
        // Créer un nouveau user sans skills
        $newUser = UserFactory::createOne([
            'email' => 'noskills@example.com',
            'plainPassword' => 'password',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'noskills@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $newToken = $response->toArray()['token'];

        $response = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $newToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame(0, $data['totalItems']);
        $this->assertCount(0, $data['member']);
    }

    // ==================== GET /me/skills/:id ====================

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

    public function testGetNonExistentMySkillReturns404(): void
    {
        static::createClient()->request('GET', '/me/skills/99999', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetMySkillByIdWithoutAuth(): void
    {
        static::createClient()->request('GET', '/me/skills/' . $this->userSkillReact->getId());
        $this->assertResponseStatusCodeSame(401);
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
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertArrayHasKey('id', $data);
        $this->assertSame(UserSkill::TYPE_TEACH, $data['type']);
        $this->assertSame(UserSkill::LEVEL_EXPERT, $data['level']);
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
            'headers' => ['Content-Type' => 'application/ld+json'],
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
            'headers' => ['Content-Type' => 'application/ld+json'],
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
            'headers' => ['Content-Type' => 'application/ld+json'],
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

    public function testPostMySkillWithInvalidLevelFails(): void
    {
        static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/' . $this->phpSkill->getId(),
                'type' => UserSkill::TYPE_TEACH,
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

    public function testPostMySkillWithoutSkillFails(): void
    {
        static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [[
                'propertyPath' => 'skill',
            ]],
        ]);
    }

    public function testPostMySkillWithoutTypeFails(): void
    {
        static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/' . $this->phpSkill->getId(),
                'level' => UserSkill::LEVEL_EXPERT,
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

    public function testPostMySkillWithNullLevelSucceeds(): void
    {
        // Le level est optionnel
        $response = static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/' . $this->phpSkill->getId(),
                'type' => UserSkill::TYPE_LEARN,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        // Vérifier que la création a réussi
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertSame(UserSkill::TYPE_LEARN, $data['type']);
    }

    public function testPostMySkillWithoutAuthFails(): void
    {
        static::createClient()->request('POST', '/me/skills', [
            'json' => [
                'skill' => '/skills/' . $this->phpSkill->getId(),
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPostMySkillWithInvalidSkillIriFails(): void
    {
        static::createClient()->request('POST', '/me/skills', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'skill' => '/skills/99999',
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(400);
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

    public function testDeleteNonExistentMySkillReturns404(): void
    {
        static::createClient()->request('DELETE', '/me/skills/99999', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteMySkillByIdWithoutAuth(): void
    {
        static::createClient()->request('DELETE', '/me/skills/' . $this->userSkillJavaScript->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteMySkillAndVerifyCountDecreases(): void
    {
        // Vérifier le count initial
        $response = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);
        $initialCount = $response->toArray()['totalItems'];

        // Supprimer une skill
        static::createClient()->request('DELETE', '/me/skills/' . $this->userSkillReact->getId(), [
            'auth_bearer' => $this->userToken,
        ]);
        $this->assertResponseStatusCodeSame(204);

        // Vérifier le nouveau count
        $response = static::createClient()->request('GET', '/me/skills', [
            'auth_bearer' => $this->userToken,
        ]);
        $newCount = $response->toArray()['totalItems'];

        $this->assertSame($initialCount - 1, $newCount);
    }
}

<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Skill;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MySkillsTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private User $user;
    private User $anotherUser;

    private HttpClientInterface $userClient;
    private HttpClientInterface $anotherUserClient;

    private string $userCsrfToken;
    private string $anotherUserCsrfToken;

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

        // User authentifié principal
        [
            $this->userClient,
            $this->userCsrfToken,
            $this->user,
        ] = $this->createAuthenticatedUser(
            email: 'test@example.com',
            password: 'password',
        );

        // Autre user authentifié
        [
            $this->anotherUserClient,
            $this->anotherUserCsrfToken,
            $this->anotherUser,
        ] = $this->createAuthenticatedUser(
            email: 'anotherUser@example.com',
            password: 'password',
        );

        // Skills de user1
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

        // Skills de anotherUser
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
    }

    // ==================== GET /me/skills ====================

    public function testGetMySkills(): void
    {
        $response = $this->userClient->request('GET', '/me/skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertArrayHasKey('@context', $data);
        self::assertSame('/contexts/MySkill', $data['@context']);
        self::assertSame('Collection', $data['@type']);
        self::assertArrayHasKey('member', $data);
        self::assertArrayHasKey('totalItems', $data);
        self::assertSame(2, $data['totalItems']);
        self::assertCount(2, $data['member']);

        $skillIris = array_column($data['member'], 'skill');
        $reactSkillIri = '/skills/'.$this->reactSkill->getId();
        $javascriptSkillIri = '/skills/'.$this->javascriptSkill->getId();

        $actualSkillIris = array_column($skillIris, '@id');

        self::assertContains($reactSkillIri, $actualSkillIris);
        self::assertContains($javascriptSkillIri, $actualSkillIris);
    }

    public function testGetMySkillsResponseStructure(): void
    {
        $response = $this->userClient->request('GET', '/me/skills');

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        $firstSkill = $data['member'][0] ?? null;
        self::assertNotNull($firstSkill);

        self::assertArrayHasKey('@id', $firstSkill);
        self::assertArrayHasKey('@type', $firstSkill);
        self::assertEquals('MySkill', $firstSkill['@type']);
        self::assertArrayHasKey('id', $firstSkill);
        self::assertArrayHasKey('skill', $firstSkill);
        self::assertArrayHasKey('type', $firstSkill);
        self::assertArrayHasKey('level', $firstSkill);
        self::assertArrayHasKey('createdAt', $firstSkill);

        self::assertIsInt($firstSkill['id']);
        self::assertIsArray($firstSkill['skill']);
        self::assertIsString($firstSkill['type']);
        self::assertIsString($firstSkill['createdAt']);
    }

    public function testGetMySkillsWithoutAuth(): void
    {
        $response = static::createClient()->request('GET', '/me/skills');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetMySkillsWhenEmpty(): void
    {
        // Créer un nouveau user sans skills
        [
            $newClient,
            $newCsrfToken,
            $newUser,
        ] = $this->createAuthenticatedUser(
            email: 'noskills@example.com',
            password: 'password',
        );

        $response = $newClient->request('GET', '/me/skills');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(0, $data['totalItems']);
        self::assertCount(0, $data['member']);
    }

    // ==================== GET /me/skills/:id ====================

    public function testGetMySkillById(): void
    {
        $userSkillId = $this->userSkillReact->getId();

        $response = $this->userClient->request('GET', '/me/skills/'.$userSkillId);

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame($userSkillId, $data['id']);
        self::assertSame('learn', $data['type']);
        self::assertSame('beginner', $data['level']);
    }

    public function testGetAnotherUsersSkillByIdFails(): void
    {
        $response = $this->userClient->request(
            'GET',
            '/me/skills/'.$this->anotherUserSkillAngular->getId()
        );

        self::assertSame(404, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame('UserSkill not found', $data['detail'] ?? null);
    }

    public function testGetNonExistentMySkillReturns404(): void
    {
        $response = $this->userClient->request('GET', '/me/skills/99999');

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetMySkillByIdWithoutAuth(): void
    {
        $response = static::createClient()->request('GET', '/me/skills/'.$this->userSkillReact->getId());

        self::assertSame(401, $response->getStatusCode());
    }

    // ==================== POST /me/skills ====================

    public function testPostNewMySkillSuccessfully(): void
    {
        $phpSkillIri = '/skills/'.$this->phpSkill->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => $phpSkillIri,
                    'type' => UserSkill::TYPE_TEACH,
                    'level' => UserSkill::LEVEL_EXPERT,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertArrayHasKey('id', $data);
        self::assertSame(UserSkill::TYPE_TEACH, $data['type']);
        self::assertSame(UserSkill::LEVEL_EXPERT, $data['level']);
    }

    public function testPostSameSkillWithDifferentTypeSuccessfully(): void
    {
        $reactSkillIri = '/skills/'.$this->reactSkill->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => $reactSkillIri,
                    'type' => UserSkill::TYPE_TEACH,
                    'level' => UserSkill::LEVEL_EXPERT,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $responseGet = $this->userClient->request('GET', '/me/skills');
        $data = $responseGet->toArray(false);

        self::assertCount(3, $data['member'], 'After POST, the user should have 3 skills.');
    }

    public function testPostExistingMySkillFails(): void
    {
        $reactSkillIri = '/skills/'.$this->userSkillReact->getSkill()->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => $reactSkillIri,
                    'type' => UserSkill::TYPE_LEARN,
                    'level' => UserSkill::LEVEL_INTERMEDIATE,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('skill', $violations[0]['propertyPath'] ?? null);
        self::assertSame('You already have this skill with this type', $violations[0]['message'] ?? null);
    }

    public function testPostMySkillWithInvalidTypeFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->phpSkill->getId(),
                    'type' => 'invalid_type',
                    'level' => UserSkill::LEVEL_BEGINNER,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('type', $violations[0]['propertyPath'] ?? null);
        self::assertSame('The type must be either "teach" or "learn"', $violations[0]['message'] ?? null);
    }

    public function testPostMySkillWithInvalidLevelFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->phpSkill->getId(),
                    'type' => UserSkill::TYPE_TEACH,
                    'level' => 'invalid_level',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('level', $violations[0]['propertyPath'] ?? null);
    }

    public function testPostMySkillWithoutSkillFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'type' => UserSkill::TYPE_TEACH,
                    'level' => UserSkill::LEVEL_EXPERT,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('skill', $violations[0]['propertyPath'] ?? null);
    }

    public function testPostMySkillWithoutTypeFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->phpSkill->getId(),
                    'level' => UserSkill::LEVEL_EXPERT,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('type', $violations[0]['propertyPath'] ?? null);
    }

    public function testPostMySkillWithNullLevelSucceeds(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/'.$this->phpSkill->getId(),
                    'type' => UserSkill::TYPE_LEARN,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('type', $data);
        self::assertSame(UserSkill::TYPE_LEARN, $data['type']);
    }

    public function testPostMySkillWithoutAuthFails(): void
    {
        $response = static::createClient()->request('POST', '/me/skills', [
            'json' => [
                'skill' => '/skills/'.$this->phpSkill->getId(),
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testPostMySkillWithInvalidSkillIriFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/skills',
            $this->userCsrfToken,
            [
                'json' => [
                    'skill' => '/skills/99999',
                    'type' => UserSkill::TYPE_TEACH,
                    'level' => UserSkill::LEVEL_EXPERT,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(400, $response->getStatusCode());
    }

    // =================== DELETE /me/skills/:id ====================

    public function testDeleteMySkillById(): void
    {
        $userSkillIdToDelete = $this->userSkillJavaScript->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/skills/'.$userSkillIdToDelete,
            $this->userCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $responseGet = $this->userClient->request('GET', '/me/skills/'.$userSkillIdToDelete);
        self::assertSame(404, $responseGet->getStatusCode(), 'The skill should no longer be found after deletion.');

        $responseList = $this->userClient->request('GET', '/me/skills');
        $dataList = $responseList->toArray(false);
        self::assertCount(1, $dataList['member'], 'Only one skill should remain in the list.');
    }

    public function testDeleteAnotherUsersSkillByIdFailsWith404(): void
    {
        $anotherUserSkillId = $this->anotherUserSkillAngular->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/skills/'.$anotherUserSkillId,
            $this->userCsrfToken
        );

        self::assertSame(404, $response->getStatusCode(), 'An attempt to delete another user\'s skill must result in a 404.');

        $responseGet = $this->anotherUserClient->request(
            'GET',
            '/me/skills/'.$anotherUserSkillId
        );

        self::assertSame(200, $responseGet->getStatusCode(), 'The other user\'s skill should still exist.');
    }

    public function testDeleteNonExistentMySkillReturns404(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/skills/99999',
            $this->userCsrfToken
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteMySkillByIdWithoutAuth(): void
    {
        $response = static::createClient()->request('DELETE', '/me/skills/'.$this->userSkillJavaScript->getId());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testDeleteMySkillAndVerifyCountDecreases(): void
    {
        // Vérifier le count initial
        $response = $this->userClient->request('GET', '/me/skills');
        self::assertSame(200, $response->getStatusCode());

        $initialData = $response->toArray(false);
        $initialCount = $initialData['totalItems'];

        // Supprimer une skill
        $responseDelete = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/skills/'.$this->userSkillReact->getId(),
            $this->userCsrfToken
        );

        self::assertSame(204, $responseDelete->getStatusCode());

        // Vérifier le nouveau count
        $responseAfter = $this->userClient->request('GET', '/me/skills');
        self::assertSame(200, $responseAfter->getStatusCode());

        $afterData = $responseAfter->toArray(false);
        $newCount = $afterData['totalItems'];

        self::assertSame($initialCount - 1, $newCount);
    }
}

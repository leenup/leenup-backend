<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Entity\Skill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class CurrentUserTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private string $csrfToken;
    private User $user;
    private Skill $skill1;
    private Skill $skill2;

    protected function setUp(): void
    {
        parent::setUp();

        // Catégorie & skills
        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill1 = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->skill2 = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $category]);

        // User authentifié via /auth (cookie) + CSRF
        [$this->client, $this->csrfToken, $this->user] = $this->createAuthenticatedUser(
            'test@example.com',
            'password'
        );

        // Enrichir le user
        $this->user->setFirstName('John');
        $this->user->setLastName('Doe');
        $this->user->setBio('Original bio');
        $this->user->setLocation('Paris, France');
        $this->user->setTimezone('Europe/Paris');
        $this->user->setLocale('fr');
        $this->user->setBirthdate(new \DateTimeImmutable('1990-01-15'));
        $this->user->setLanguages(['fr', 'en']);
        $this->user->setExchangeFormat('visio');
        $this->user->setLearningStyles(['calm_explanations', 'hands_on']);
        $this->user->setIsMentor(true);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->flush();

        // Ajouter des skills au user
        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill1,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->user,
            'skill' => $this->skill2,
            'type' => UserSkill::TYPE_LEARN,
            'level' => UserSkill::LEVEL_BEGINNER,
        ]);
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = $this->client->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@type' => 'User',
            'email' => 'test@example.com',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
            'roles' => ['ROLE_USER'],
        ]);

        $data = $response->toArray(false);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('plainPassword', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('userSkills', $data);
        $this->assertCount(2, $data['userSkills']);
        $this->assertArrayHasKey('birthdate', $data);
        $this->assertArrayHasKey('languages', $data);
        $this->assertArrayHasKey('exchangeFormat', $data);
        $this->assertArrayHasKey('learningStyles', $data);
    }

    public function testGetCurrentUserProfileIncludesUserSkills(): void
    {
        $response = $this->client->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray(false);

        $this->assertArrayHasKey('userSkills', $data);
        $this->assertIsArray($data['userSkills']);
        $this->assertCount(2, $data['userSkills']);

        $firstSkill = $data['userSkills'][0];
        $this->assertArrayHasKey('@id', $firstSkill);
        $this->assertArrayHasKey('@type', $firstSkill);
        $this->assertEquals('UserSkill', $firstSkill['@type']);
        $this->assertArrayHasKey('skill', $firstSkill);

        $this->assertArrayHasKey('category', $firstSkill['skill']);
        $this->assertArrayHasKey('title', $firstSkill['skill']['category']);
    }

    public function testGetCurrentUserProfileUserSkillsHaveCorrectData(): void
    {
        $response = $this->client->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray(false);

        $skillTitles = array_map(fn($us) => $us['skill']['title'], $data['userSkills']);

        $this->assertContains('React', $skillTitles);
        $this->assertContains('Vue.js', $skillTitles);
    }

    public function testGetCurrentUserProfileWithoutAuthentication(): void
    {
        static::createClient()->request('GET', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetCurrentUserProfileWithInvalidToken(): void
    {
        static::createClient()->request('GET', '/me', [
            'auth_bearer' => 'invalid_token_12345',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testDifferentUsersGetTheirOwnProfile(): void
    {
        $category2 = CategoryFactory::createOne(['title' => 'Design']);
        $skill3 = SkillFactory::createOne(['title' => 'Figma', 'category' => $category2]);

        [$client2, $csrfToken2, $user2] = $this->createAuthenticatedUser(
            'user2@example.com',
            'password'
        );

        UserSkillFactory::createOne([
            'owner' => $user2,
            'skill' => $skill3,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_ADVANCED,
        ]);

        $user2->setFirstName('Jane');
        $user2->setLastName('Smith');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $profile1 = $this->client->request('GET', '/me');
        $this->assertResponseIsSuccessful();
        $data1 = $profile1->toArray(false);
        $this->assertEquals('test@example.com', $data1['email']);
        $this->assertEquals('John', $data1['firstName']);
        $this->assertCount(2, $data1['userSkills']);

        $profile2 = $client2->request('GET', '/me');
        $this->assertResponseIsSuccessful();
        $data2 = $profile2->toArray(false);
        $this->assertEquals('user2@example.com', $data2['email']);
        $this->assertEquals('Jane', $data2['firstName']);
        $this->assertCount(1, $data2['userSkills']);

        $this->assertNotEquals($data1['id'], $data2['id']);

        $skillTitles1 = array_map(fn($us) => $us['skill']['title'], $data1['userSkills']);
        $skillTitles2 = array_map(fn($us) => $us['skill']['title'], $data2['userSkills']);

        $this->assertContains('React', $skillTitles1);
        $this->assertNotContains('Figma', $skillTitles1);
        $this->assertContains('Figma', $skillTitles2);
        $this->assertNotContains('React', $skillTitles2);
    }

    public function testCurrentUserProfileResponseStructure(): void
    {
        $response = $this->client->request('GET', '/me');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray(false);

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('roles', $data);
        $this->assertArrayHasKey('firstName', $data);
        $this->assertArrayHasKey('lastName', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('userSkills', $data);
        $this->assertArrayHasKey('birthdate', $data);
        $this->assertArrayHasKey('languages', $data);
        $this->assertArrayHasKey('exchangeFormat', $data);
        $this->assertArrayHasKey('learningStyles', $data);
        $this->assertArrayHasKey('isMentor', $data);
        $this->assertIsInt($data['id']);
        $this->assertIsString($data['email']);
        $this->assertIsArray($data['roles']);
        $this->assertIsString($data['firstName']);
        $this->assertIsString($data['lastName']);
        $this->assertIsArray($data['userSkills']);
        $this->assertEquals('User', $data['@type']);
    }

    public function testCurrentUserWithNoSkills(): void
    {
        [$clientNoSkills, $csrfNoSkills, $userNoSkills] = $this->createAuthenticatedUser(
            'noskills@example.com',
            'password'
        );

        $profile = $clientNoSkills->request('GET', '/me');
        $this->assertResponseIsSuccessful();
        $data = $profile->toArray(false);

        $this->assertArrayHasKey('userSkills', $data);
        $this->assertIsArray($data['userSkills']);
        $this->assertCount(0, $data['userSkills']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['email' => 'newemail@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);

        $client = static::createClient();
        $client->request('POST', '/auth', [
            'json' => ['email' => 'newemail@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();

        $profile = $client->request('GET', '/me');
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);
    }

    public function testUpdateCurrentUserFirstName(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['firstName' => 'UpdatedFirstName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['firstName' => 'UpdatedFirstName']);
    }

    public function testUpdateCurrentUserLastName(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['lastName' => 'UpdatedLastName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['lastName' => 'UpdatedLastName']);
    }

    public function testUpdateCurrentUserBio(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['bio' => 'This is my updated bio'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['bio' => 'This is my updated bio']);
    }

    public function testUpdateCurrentUserLocation(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['location' => 'London, UK'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['location' => 'London, UK']);
    }

    public function testUpdateCurrentUserTimezone(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['timezone' => 'America/New_York'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['timezone' => 'America/New_York']);
    }

    public function testUpdateCurrentUserLocale(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['locale' => 'en'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['locale' => 'en']);
    }


    public function testUpdateCurrentUserAvatarUrlFromUploadedMedia(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'avatar_profile_');
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7ZxWQAAAAASUVORK5CYII='));

        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $filePath,
            'profile.png',
            'image/png',
            null,
            true
        );

        $uploadResponse = $this->requestUnsafe($this->client, 'POST', '/media_objects', $this->csrfToken, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => [
                    'directory' => 'profile',
                ],
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $contentUrl = $uploadResponse->toArray(false)['contentUrl'];

        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['avatarUrl' => $contentUrl],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['avatarUrl' => $contentUrl]);

        @unlink($filePath);
    }

    public function testUpdateCurrentUserAvatarUrl(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['avatarUrl' => 'https://example.com/avatar.jpg'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['avatarUrl' => 'https://example.com/avatar.jpg']);
    }

    public function testUpdateMultipleFieldsAtOnce(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => [
                'firstName' => 'NewFirstName',
                'lastName' => 'NewLastName',
                'bio' => 'New bio',
                'location' => 'Berlin, Germany',
                'timezone' => 'Europe/Berlin',
                'locale' => 'de',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'firstName' => 'NewFirstName',
            'lastName' => 'NewLastName',
            'bio' => 'New bio',
            'location' => 'Berlin, Germany',
            'timezone' => 'Europe/Berlin',
            'locale' => 'de',
        ]);
    }

    public function testUpdateCurrentUserBirthdate(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['birthdate' => '1992-03-10'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray();

        $this->assertEquals('1992-03-10', substr($data['birthdate'], 0, 10));
    }

    public function testUpdateCurrentUserLanguagesAndLearningStyles(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => [
                'languages' => ['en', 'es'],
                'learningStyles' => ['concrete_examples', 'structured'],
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray();

        $this->assertEquals(['en', 'es'], $data['languages']);
        $this->assertEquals(['concrete_examples', 'structured'], $data['learningStyles']);
    }

    public function testUpdateCurrentUserExchangeFormatAndIsMentor(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => [
                'exchangeFormat' => 'chat',
                'isMentor' => false,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray();

        $this->assertEquals('chat', $data['exchangeFormat']);
        // isMentor reste contrôlé côté back (ex: non modifiable via /me)
        $this->assertTrue($data['isMentor']);
    }

    public function testUpdateCurrentUserProfilesIsIgnored(): void
    {
        $this->user->setProfiles(['mentor']);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['profiles' => ['student']],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray(false);

        $this->assertSame(['mentor'], $data['profiles']);
    }

    public function testUpdateCurrentUserWithoutAuthentication(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'json' => ['firstName' => 'Hacker'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUpdateCurrentUserEmailWithInvalidEmail(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['email' => 'invalid-email'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [['propertyPath' => 'email']],
        ]);
    }

    public function testUpdateCurrentUserEmailWithDuplicateEmail(): void
    {
        UserFactory::createOne(['email' => 'existing@example.com']);

        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['email' => 'existing@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['email' => ''],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserAvatarUrlWithInvalidUrl(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['avatarUrl' => 'not-a-valid-url'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserBioTooLong(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['bio' => str_repeat('a', 501)],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserFirstNameTooShort(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['firstName' => 'A'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCannotUpdateIsActiveViaMe(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['isActive' => false],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // champ ignoré, mais la requête reste OK
        $this->assertResponseIsSuccessful();
    }

    public function testCannotUpdateLastLoginAtViaMe(): void
    {
        $this->requestUnsafe($this->client, 'PATCH', '/me', $this->csrfToken, [
            'json' => ['lastLoginAt' => '2025-01-01T00:00:00+00:00'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
    }

    // ==================== POST /me/addProfiles ====================

    public function testAddProfileToCurrentUser(): void
    {
        $this->user->setProfiles(['mentor']);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->requestUnsafe($this->client, 'POST', '/me/addProfiles', $this->csrfToken, [
            'json' => ['profile' => 'student'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray(false);

        $this->assertCount(2, $data['profiles']);
        $this->assertContains('mentor', $data['profiles']);
        $this->assertContains('student', $data['profiles']);
    }

    public function testAddProfileAlreadyPresentKeepsProfiles(): void
    {
        $this->user->setProfiles(['mentor']);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->requestUnsafe($this->client, 'POST', '/me/addProfiles', $this->csrfToken, [
            'json' => ['profile' => 'mentor'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->request('GET', '/me');
        $data = $response->toArray(false);

        $this->assertSame(['mentor'], $data['profiles']);
    }

    public function testAddProfileWithoutAuthentication(): void
    {
        static::createClient()->request('POST', '/me/addProfiles', [
            'json' => ['profile' => 'mentor'],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ==================== DELETE /me ====================

    public function testDeleteCurrentUserAccount(): void
    {
        $this->requestUnsafe($this->client, 'DELETE', '/me', $this->csrfToken);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        $this->requestUnsafe($this->client, 'DELETE', '/me', $this->csrfToken);
        $this->assertResponseStatusCodeSame(204);

        // même client, mais user supprimé → /me doit renvoyer 401
        $this->client->request('GET', '/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        $this->requestUnsafe($this->client, 'DELETE', '/me', $this->csrfToken);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithoutAuthentication(): void
    {
        static::createClient()->request('DELETE', '/me');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountWithInvalidToken(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => 'invalid_token']);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountCannotBeUndone(): void
    {
        $this->requestUnsafe($this->client, 'DELETE', '/me', $this->csrfToken);
        $this->assertResponseStatusCodeSame(204);

        $em = self::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
        $this->assertNull($user);

        static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountAlsoDeletesUserSkills(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $userSkills = $em->getRepository(UserSkill::class)->findBy(['owner' => $this->user]);
        $userSkillIds = array_map(fn($us) => $us->getId(), $userSkills);

        $this->assertCount(2, $userSkillIds);

        $this->requestUnsafe($this->client, 'DELETE', '/me', $this->csrfToken);
        $this->assertResponseStatusCodeSame(204);

        $em->clear();

        foreach ($userSkillIds as $id) {
            $this->assertNull($em->getRepository(UserSkill::class)->find($id));
        }
    }
}

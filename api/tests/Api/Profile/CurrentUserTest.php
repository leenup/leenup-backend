<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class CurrentUserTest extends ApiTestCase
{
    use Factories;

    private string $userToken;
    private $user;
    private $skill1;
    private $skill2;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer une catégorie et des skills
        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill1 = SkillFactory::createOne(['title' => 'React', 'category' => $category]);
        $this->skill2 = SkillFactory::createOne(['title' => 'Vue.js', 'category' => $category]);

        // Créer un utilisateur et obtenir son token
        $this->user = UserFactory::createOne([
            'email' => 'test@example.com',
            'plainPassword' => 'password',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'bio' => 'Original bio',
            'location' => 'Paris, France',
            'timezone' => 'Europe/Paris',
            'locale' => 'fr',
            // nouveaux champs
            'birthdate' => new \DateTimeImmutable('1990-01-15'),
            'languages' => ['fr', 'en'],
            'exchangeFormat' => 'visio',
            'learningStyles' => ['calm_explanations', 'hands_on'],
            'isMentor' => true,
        ]);

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

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'test@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->userToken = $response->toArray()['token'];
    }

    // ==================== GET /me ====================

    public function testGetCurrentUserProfile(): void
    {
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);

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

        $data = $response->toArray();
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
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

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
        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

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

        $user2 = UserFactory::createOne([
            'email' => 'user2@example.com',
            'plainPassword' => 'password',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
        ]);

        UserSkillFactory::createOne([
            'owner' => $user2,
            'skill' => $skill3,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_ADVANCED,
        ]);

        $response2 = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $token2 = $response2->toArray()['token'];

        $profile1 = static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseIsSuccessful();
        $data1 = $profile1->toArray();
        $this->assertEquals('test@example.com', $data1['email']);
        $this->assertEquals('John', $data1['firstName']);
        $this->assertCount(2, $data1['userSkills']);

        $profile2 = static::createClient()->request('GET', '/me', ['auth_bearer' => $token2]);
        $this->assertResponseIsSuccessful();
        $data2 = $profile2->toArray();
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
        $response = static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

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
        $userNoSkills = UserFactory::createOne([
            'email' => 'noskills@example.com',
            'plainPassword' => 'password',
            'firstName' => 'No',
            'lastName' => 'Skills',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'noskills@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $tokenNoSkills = $response->toArray()['token'];

        $profile = static::createClient()->request('GET', '/me', ['auth_bearer' => $tokenNoSkills]);
        $this->assertResponseIsSuccessful();
        $data = $profile->toArray();

        $this->assertArrayHasKey('userSkills', $data);
        $this->assertIsArray($data['userSkills']);
        $this->assertCount(0, $data['userSkills']);
    }

    // ==================== PATCH /me ====================

    public function testUpdateCurrentUserEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'newemail@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);

        $newAuthResponse = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'newemail@example.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $newToken = $newAuthResponse->toArray()['token'];

        $profile = static::createClient()->request('GET', '/me', ['auth_bearer' => $newToken]);
        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['email' => 'newemail@example.com']);
    }

    public function testUpdateCurrentUserFirstName(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['firstName' => 'UpdatedFirstName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['firstName' => 'UpdatedFirstName']);
    }

    public function testUpdateCurrentUserLastName(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['lastName' => 'UpdatedLastName'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['lastName' => 'UpdatedLastName']);
    }

    public function testUpdateCurrentUserBio(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['bio' => 'This is my updated bio'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['bio' => 'This is my updated bio']);
    }

    public function testUpdateCurrentUserLocation(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['location' => 'London, UK'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['location' => 'London, UK']);
    }

    public function testUpdateCurrentUserTimezone(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['timezone' => 'America/New_York'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['timezone' => 'America/New_York']);
    }

    public function testUpdateCurrentUserLocale(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['locale' => 'en'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['locale' => 'en']);
    }

    public function testUpdateCurrentUserAvatarUrl(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['avatarUrl' => 'https://example.com/avatar.jpg'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains(['avatarUrl' => 'https://example.com/avatar.jpg']);
    }

    public function testUpdateMultipleFieldsAtOnce(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
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
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['birthdate' => '1992-03-10'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);
        $data = $response->toArray();

        $this->assertEquals('1992-03-10', substr($data['birthdate'], 0, 10));
    }

    public function testUpdateCurrentUserLanguagesAndLearningStyles(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'languages' => ['en', 'es'],
                'learningStyles' => ['concrete_examples', 'structured'],
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);
        $data = $response->toArray();

        $this->assertEquals(['en', 'es'], $data['languages']);
        $this->assertEquals(['concrete_examples', 'structured'], $data['learningStyles']);
    }

    public function testUpdateCurrentUserExchangeFormatAndIsMentor(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'exchangeFormat' => 'chat',
                'isMentor' => false,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $response = static::createClient()->request('GET', '/me', [
            'auth_bearer' => $this->userToken,
        ]);
        $data = $response->toArray();

        $this->assertEquals('chat', $data['exchangeFormat']);
        $this->assertTrue($data['isMentor']);
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
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
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

        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => 'existing@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserEmailWithEmptyEmail(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['email' => ''],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserAvatarUrlWithInvalidUrl(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['avatarUrl' => 'not-a-valid-url'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserBioTooLong(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['bio' => str_repeat('a', 501)],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUpdateCurrentUserFirstNameTooShort(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['firstName' => 'A'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testCannotUpdateIsActiveViaMe(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['isActive' => false],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testCannotUpdateLastLoginAtViaMe(): void
    {
        static::createClient()->request('PATCH', '/me', [
            'auth_bearer' => $this->userToken,
            'json' => ['lastLoginAt' => '2025-01-01T00:00:00+00:00'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
    }

    // ==================== DELETE /me ====================

    public function testDeleteCurrentUserAccount(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteCurrentUserAccountMakesTokenInvalid(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(204);

        static::createClient()->request('GET', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testDeleteCurrentUserAccountPreventsLogin(): void
    {
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
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
        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
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

        static::createClient()->request('DELETE', '/me', ['auth_bearer' => $this->userToken]);
        $this->assertResponseStatusCodeSame(204);

        $em->clear();

        foreach ($userSkillIds as $id) {
            $this->assertNull($em->getRepository(UserSkill::class)->find($id));
        }
    }
}

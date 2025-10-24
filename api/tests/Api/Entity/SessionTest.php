<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Session;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class SessionTest extends ApiTestCase
{
    use Factories;

    private string $userToken;
    private string $mentorToken;
    private $user;
    private $mentor;
    private $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill = SkillFactory::createOne(['title' => 'React', 'category' => $category]);

        $this->user = UserFactory::createOne([
            'email' => 'student@test.com',
            'plainPassword' => 'password',
        ]);

        $this->mentor = UserFactory::createOne([
            'email' => 'mentor@test.com',
            'plainPassword' => 'password',
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $this->skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'student@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->userToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'mentor@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->mentorToken = $response->toArray()['token'];
    }

    public function testGetSessionsCollection(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = static::createClient()->request('GET', '/sessions', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Session',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetSessionsCollectionWithoutAuth(): void
    {
        static::createClient()->request('GET', '/sessions');
        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetSessionById(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = static::createClient()->request('GET', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(Session::STATUS_PENDING, $data['status']);
    }

    public function testCreateSessionAsStudent(): void
    {
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        $response = static::createClient()->request('POST', '/sessions', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'mentor' => '/users/' . $this->mentor->getId(),
                'skill' => '/skills/' . $this->skill->getId(),
                'scheduledAt' => $scheduledAt,
                'duration' => 60,
                'location' => 'Zoom',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertEquals(Session::STATUS_PENDING, $data['status']);
        $this->assertEquals('/users/' . $this->user->getId(), $data['student']);
    }

    public function testCannotCreateSessionWithSelfAsMentor(): void
    {
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        static::createClient()->request('POST', '/sessions', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'mentor' => '/users/' . $this->user->getId(),
                'skill' => '/skills/' . $this->skill->getId(),
                'scheduledAt' => $scheduledAt,
                'duration' => 60,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'mentor',
                    'message' => 'You cannot be your own mentor',
                ],
            ],
        ]);
    }

    public function testCannotCreateSessionIfMentorDoesNotHaveSkill(): void
    {
        $anotherSkill = SkillFactory::createOne(['title' => 'Vue.js']);
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        static::createClient()->request('POST', '/sessions', [
            'auth_bearer' => $this->userToken,
            'json' => [
                'mentor' => '/users/' . $this->mentor->getId(),
                'skill' => '/skills/' . $anotherSkill->getId(),
                'scheduledAt' => $scheduledAt,
                'duration' => 60,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'mentor',
                    'message' => 'The mentor must have this skill with type "teach"',
                ],
            ],
        ]);
    }

    public function testMentorCanConfirmSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_CONFIRMED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(Session::STATUS_CONFIRMED, $data['status']);
    }

    public function testStudentCannotConfirmSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'status' => Session::STATUS_CONFIRMED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'Only the mentor can confirm a session',
        ]);
    }

    public function testMentorCanCompleteSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_COMPLETED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(Session::STATUS_COMPLETED, $data['status']);
    }

    public function testCannotCompletePendingSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_COMPLETED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'A pending session must be confirmed before being completed',
                ],
            ],
        ]);
    }

    public function testBothCanCancelSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'status' => Session::STATUS_CANCELLED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(Session::STATUS_CANCELLED, $data['status']);
    }

    public function testCannotChangeStatusFromCancelled(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_CANCELLED,
        ]);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_CONFIRMED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'status',
                    'message' => 'Cannot change status from cancelled',
                ],
            ],
        ]);
    }

    public function testCannotChangeMentorStudentOrSkill(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $anotherMentor = UserFactory::createOne(['email' => 'another-mentor@test.com']);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'mentor' => '/users/' . $anotherMentor->getId(),
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'mentor',
                    'message' => 'You cannot change the mentor, student, or skill of a session',
                ],
            ],
        ]);
    }

    // ========================================
    // 🆕 NOUVEAUX TESTS POUR LE VOTER
    // ========================================

    /**
     * Test que le MENTOR peut modifier l'horaire (scheduledAt)
     */
    public function testMentorCanUpdateSchedule(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $newScheduledAt = (new \DateTimeImmutable('+2 weeks'))->format(\DateTimeInterface::ATOM);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'scheduledAt' => $newScheduledAt,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals($newScheduledAt, $data['scheduledAt']);
    }

    /**
     * Test que le STUDENT ne peut PAS modifier l'horaire (scheduledAt)
     * 🔥 C'est LE test qui valide notre fix de sécurité !
     */
    public function testStudentCannotUpdateSchedule(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $newScheduledAt = (new \DateTimeImmutable('+2 weeks'))->format(\DateTimeInterface::ATOM);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'scheduledAt' => $newScheduledAt,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only the mentor can modify the schedule',
        ]);
    }

    /**
     * Test que le MENTOR peut modifier la durée
     */
    public function testMentorCanUpdateDuration(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'duration' => 90,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(90, $data['duration']);
    }

    /**
     * Test que le STUDENT ne peut PAS modifier la durée
     * 🔥 Autre test critique pour valider le fix !
     */
    public function testStudentCannotUpdateDuration(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'duration' => 90,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            'detail' => 'Only the mentor can modify the schedule',
        ]);
    }

    /**
     * Test que le STUDENT peut modifier les notes (reste autorisé)
     */
    public function testStudentCanUpdateNotes(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'notes' => 'Original notes',
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'notes' => 'Updated notes by student',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals('Updated notes by student', $data['notes']);
    }

    /**
     * Test que le STUDENT peut modifier la location (reste autorisé)
     */
    public function testStudentCanUpdateLocation(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'location' => 'Zoom',
        ]);

        $response = static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->userToken,
            'json' => [
                'location' => 'Google Meet',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals('Google Meet', $data['location']);
    }

    // ========================================
    // Tests existants (inchangés)
    // ========================================

    public function testGetMySessionsAsMentor(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = static::createClient()->request('GET', '/me/sessions/as-mentor', [
            'auth_bearer' => $this->mentorToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetMySessionsAsStudent(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->user,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = static::createClient()->request('GET', '/me/sessions/as-student', [
            'auth_bearer' => $this->userToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }
}

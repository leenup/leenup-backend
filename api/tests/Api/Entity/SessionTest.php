<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\MentorAvailabilityRuleFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class SessionTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $studentClient;
    private HttpClientInterface $mentorClient;

    private string $studentCsrfToken;
    private string $mentorCsrfToken;

    private User $student;
    private User $mentor;
    private $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill = SkillFactory::createOne(['title' => 'React', 'category' => $category]);

        // On crée le student et le mentor via le trait (auth + cookies + CSRF)
        [
            $this->studentClient,
            $this->studentCsrfToken,
            $this->student,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-session'),
            password: 'password',
        );

        [
            $this->mentorClient,
            $this->mentorCsrfToken,
            $this->mentor,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-session'),
            password: 'password',
        );

        // Le mentor doit avoir la skill en "teach"
        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $this->skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => 'one_shot',
            'startsAt' => new \DateTimeImmutable('now'),
            'endsAt' => new \DateTimeImmutable('+3 months'),
        ]);
    }

    // =====================================================
    // LECTURE / COLLECTIONS
    // =====================================================

    public function testGetSessionsCollection(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->studentClient->request('GET', '/sessions');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('/contexts/Session', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetSessionsCollectionWithoutAuth(): void
    {
        $response = static::createClient()->request('GET', '/sessions');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetSessionById(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->studentClient->request('GET', '/sessions/'.$session->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(Session::STATUS_PENDING, $data['status'] ?? null);
    }

    // =====================================================
    // CRÉATION
    // =====================================================

    public function testCreateSessionAsStudent(): void
    {
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => $scheduledAt,
                    'duration' => 60,
                    'location' => 'Zoom',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(Session::STATUS_PENDING, $data['status'] ?? null);
        self::assertSame('/users/'.$this->student->getId(), $data['student'] ?? null);

        $em = self::getContainer()->get('doctrine')->getManager();
        $student = $em->getRepository(User::class)->find($this->student->getId());
        self::assertSame(0, $student->getTokenBalance());
    }

    public function testCannotCreateSessionWithoutTokens(): void
    {
        [
            $studentClient,
            $studentCsrfToken,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-no-token'),
            password: 'password',
            extraData: ['tokenBalance' => 0],
        );

        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $studentClient,
            'POST',
            '/sessions',
            $studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => $scheduledAt,
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('student', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'You need at least 1 token to join a session as a student',
            $violations[0]['message'] ?? null
        );
    }

    public function testCannotCreateSessionWithSelfAsMentor(): void
    {
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->student->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => $scheduledAt,
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('mentor', $violations[0]['propertyPath'] ?? null);
        self::assertSame('You cannot be your own mentor', $violations[0]['message'] ?? null);
    }

    public function testCannotCreateSessionIfMentorDoesNotHaveSkill(): void
    {
        $anotherSkill = SkillFactory::createOne(['title' => 'Vue.js']);
        $scheduledAt = (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$anotherSkill->getId(),
                    'scheduledAt' => $scheduledAt,
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('mentor', $violations[0]['propertyPath'] ?? null);
        self::assertSame('The mentor must have this skill with type "teach"', $violations[0]['message'] ?? null);
    }

    public function testCannotCreateSessionOutsideMentorAvailability(): void
    {
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => (new \DateTimeImmutable('+6 months'))->format(\DateTimeInterface::ATOM),
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertSame('This date is not available for the selected mentor', $data['violations'][0]['message'] ?? null);
    }

    public function testCannotCreateSessionWhenOverlappingMentorActiveSession(): void
    {
        $existingStart = new \DateTimeImmutable('+10 days 10:00');

        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => $existingStart,
            'duration' => 60,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => $existingStart->modify('+30 minutes')->format(\DateTimeInterface::ATOM),
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertSame('This date is not available for the selected mentor', $data['violations'][0]['message'] ?? null);
    }

    public function testCanCreateSessionWhenMentorHasNoAvailabilityRules(): void
    {
        $anotherMentor = UserFactory::createOne([
            'email' => $this->uniqueEmail('mentor-without-rules'),
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        UserSkillFactory::createOne([
            'owner' => $anotherMentor,
            'skill' => $this->skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_ADVANCED,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$anotherMentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => (new \DateTimeImmutable('+5 months'))->format(\DateTimeInterface::ATOM),
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());
    }

    // =====================================================
    // CHANGEMENT DE STATUS
    // =====================================================

    public function testMentorCanConfirmSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId()."/confirm",
            $this->mentorCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(Session::STATUS_CONFIRMED, $data['status'] ?? null);
    }

    public function testStudentCannotConfirmSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId()."/confirm",
            $this->studentCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Only the mentor can confirm a session', $data['detail'] ?? null);
    }

    public function testMentorCanCompleteSession(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $mentor = $em->getRepository(User::class)->find($this->mentor->getId());
        $mentor->setTokenBalance(0);
        $em->flush();

        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId()."/complete",
            $this->mentorCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(Session::STATUS_COMPLETED, $data['status'] ?? null);

        $em->clear();
        $refreshedMentor = $em->getRepository(User::class)->find($this->mentor->getId());
        self::assertSame(1, $refreshedMentor->getTokenBalance());
    }

    public function testStudentCannotCompleteSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId().'/complete',
            $this->studentCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Only the mentor can mark a session as completed', $data['detail'] ?? null);
    }

    public function testCannotCompletePendingSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId()."/complete",
            $this->mentorCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('status', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'A pending session must be confirmed before being completed',
            $violations[0]['message'] ?? null
        );
    }

    public function testBothCanCancelSession(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId()."/cancel",
            $this->studentCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(Session::STATUS_CANCELLED, $data['status'] ?? null);
    }

    public function testCannotChangeStatusFromCancelled(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CANCELLED,
        ]);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId()."/confirm",
            $this->mentorCsrfToken,
            [
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('status', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'Cannot change status from cancelled',
            $violations[0]['message'] ?? null
        );
    }

    public function testCannotChangeMentorStudentOrSkill(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $anotherMentor = UserFactory::createOne([
            'email' => $this->uniqueEmail('another-mentor'),
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$anotherMentor->getId(),
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('mentor', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'You cannot change the mentor, student, or skill of a session',
            $violations[0]['message'] ?? null
        );
    }

    // =====================================================
    // NOUVEAUX TESTS VOTER (schedule / duration / notes / location)
    // =====================================================

    public function testMentorCanUpdateSchedule(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $newScheduledAt = (new \DateTimeImmutable('+2 weeks'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->mentorCsrfToken,
            [
                'json' => [
                    'scheduledAt' => $newScheduledAt,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame($newScheduledAt, $data['scheduledAt'] ?? null);
    }

    public function testStudentCannotUpdateSchedule(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $newScheduledAt = (new \DateTimeImmutable('+2 weeks'))->format(\DateTimeInterface::ATOM);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'scheduledAt' => $newScheduledAt,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Only the mentor can modify the schedule', $data['detail'] ?? null);
    }

    public function testMentorCanUpdateDuration(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->mentorCsrfToken,
            [
                'json' => [
                    'duration' => 90,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(90, $data['duration'] ?? null);
    }

    public function testStudentCannotUpdateDuration(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'duration' => 90,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Only the mentor can modify the schedule', $data['detail'] ?? null);
    }

    public function testStudentCanUpdateNotes(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'notes' => 'Original notes',
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'notes' => 'Updated notes by student',
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Updated notes by student', $data['notes'] ?? null);
    }

    public function testStudentCanUpdateLocation(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'location' => 'Zoom',
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'location' => 'Google Meet',
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Google Meet', $data['location'] ?? null);
    }

    // =====================================================
    // ENDPOINTS /me
    // =====================================================

    public function testGetMySessionsAsMentor(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = $this->mentorClient->request('GET', '/me/sessions/as-mentor');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetMySessionsAsStudent(): void
    {
        SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->studentClient->request('GET', '/me/sessions/as-student');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }
}

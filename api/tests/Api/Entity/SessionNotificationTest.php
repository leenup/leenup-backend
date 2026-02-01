<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\NotificationFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class SessionNotificationTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $mentorClient;
    private HttpClientInterface $studentClient;

    private string $mentorCsrfToken;
    private string $studentCsrfToken;

    private User $mentor;
    private User $student;
    private $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Programming']);

        $this->skill = SkillFactory::createOne([
            'title' => 'PHP',
            'category' => $category,
        ]);

        // Création des users via le trait (user + /auth + cookies + CSRF)
        [
            $this->mentorClient,
            $this->mentorCsrfToken,
            $this->mentor,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-session-notif'),
            password: 'password',
        );

        [
            $this->studentClient,
            $this->studentCsrfToken,
            $this->student,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-session-notif'),
            password: 'password',
        );

        // Forcer les noms pour matcher les assertions ("John Mentor", "Alice Student")
        $em = self::getContainer()->get('doctrine')->getManager();
        $this->mentor->setFirstName('John');
        $this->mentor->setLastName('Mentor');
        $this->student->setFirstName('Alice');
        $this->student->setLastName('Student');
        $em->flush();

        // Le mentor doit avoir la skill en "teach"
        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $this->skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);
    }

    // ========================================
    // TESTS NOTIFICATION CRÉATION DE SESSION
    // ========================================

    public function testMentorReceivesNotificationWhenSessionIsCreated(): void
    {
        $notificationsBefore = NotificationFactory::count(['user' => $this->mentor]);

        // Student crée une session
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM),
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Vérifier qu'une notification a été créée pour le mentor
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);

        // Récupérer la notification
        $notification = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_SESSION_REQUESTED],
            ['createdAt' => 'DESC']
        )[0];

        self::assertEquals(Notification::TYPE_SESSION_REQUESTED, $notification->getType());
        self::assertEquals('Nouvelle demande de session', $notification->getTitle());
        self::assertStringContainsString('Alice Student', $notification->getContent());
        self::assertStringContainsString('PHP', $notification->getContent());
        self::assertStringStartsWith('/sessions/', $notification->getLink());
    }

    public function testStudentDoesNotReceiveNotificationWhenCreatingSession(): void
    {
        $notificationsBefore = NotificationFactory::count(['user' => $this->student]);

        // Student crée une session
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/sessions',
            $this->studentCsrfToken,
            [
                'json' => [
                    'mentor' => '/users/'.$this->mentor->getId(),
                    'skill' => '/skills/'.$this->skill->getId(),
                    'scheduledAt' => (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM),
                    'duration' => 60,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Le student ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        self::assertEquals($notificationsBefore, $notificationsAfter);
    }

    // ========================================
    // TESTS NOTIFICATION SESSION CONFIRMÉE
    // ========================================

    public function testStudentReceivesNotificationWhenSessionIsConfirmed(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->student]);

        // Mentor confirme la session
        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->mentorCsrfToken,
            [
                'json' => [
                    'status' => Session::STATUS_CONFIRMED,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        // Vérifier qu'une notification a été créée pour le student
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);

        $notification = NotificationFactory::findBy(
            ['user' => $this->student, 'type' => Notification::TYPE_SESSION_CONFIRMED],
            ['createdAt' => 'DESC']
        )[0];

        self::assertEquals(Notification::TYPE_SESSION_CONFIRMED, $notification->getType());
        self::assertEquals('Session confirmée', $notification->getTitle());

        // On ne vérifie plus le nom exact du mentor, mais le message clé + la skill
        self::assertStringContainsString('a confirmé votre session', $notification->getContent());
        self::assertStringContainsString('PHP', $notification->getContent());
    }

    // ========================================
    // TESTS NOTIFICATION SESSION ANNULÉE
    // ========================================

    public function testBothParticipantsReceiveNotificationWhenSessionIsCancelled(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => new \DateTimeImmutable('+1 week'),
            'duration' => 60,
        ]);

        $mentorNotifsBefore = NotificationFactory::count(['user' => $this->mentor]);
        $studentNotifsBefore = NotificationFactory::count(['user' => $this->student]);

        // Student annule la session
        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'status' => Session::STATUS_CANCELLED,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        // Les DEUX doivent avoir reçu une notification
        $mentorNotifsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $studentNotifsAfter = NotificationFactory::count(['user' => $this->student]);

        self::assertEquals($mentorNotifsBefore + 1, $mentorNotifsAfter);
        self::assertEquals($studentNotifsBefore + 1, $studentNotifsAfter);

        // Vérifier la notification du mentor - filtrée par type
        $mentorNotif = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_SESSION_CANCELLED],
            ['createdAt' => 'DESC']
        )[0];

        self::assertEquals(Notification::TYPE_SESSION_CANCELLED, $mentorNotif->getType());
        self::assertEquals('Session annulée', $mentorNotif->getTitle());

        // Vérifier la notification du student - filtrée par type
        $studentNotif = NotificationFactory::findBy(
            ['user' => $this->student, 'type' => Notification::TYPE_SESSION_CANCELLED],
            ['createdAt' => 'DESC']
        )[0];

        self::assertEquals(Notification::TYPE_SESSION_CANCELLED, $studentNotif->getType());
        self::assertEquals('Session annulée', $studentNotif->getTitle());
    }

    // ========================================
    // TESTS NOTIFICATION SESSION COMPLÉTÉE
    // ========================================

    public function testStudentReceivesNotificationWhenSessionIsCompleted(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->student]);

        // Mentor marque la session comme complétée
        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->mentorCsrfToken,
            [
                'json' => [
                    'status' => Session::STATUS_COMPLETED,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        // Vérifier qu'une notification a été créée pour le student
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);

        $notification = NotificationFactory::findBy(
            ['user' => $this->student, 'type' => Notification::TYPE_SESSION_COMPLETED],
            ['createdAt' => 'DESC']
        )[0];

        self::assertEquals(Notification::TYPE_SESSION_COMPLETED, $notification->getType());
        self::assertEquals('Session terminée', $notification->getTitle());
        self::assertStringContainsString('laisser un avis', $notification->getContent());
    }

    public function testMentorDoesNotReceiveNotificationWhenSessionIsCompleted(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->mentor]);

        // Mentor marque la session comme complétée
        $response = $this->requestUnsafe(
            $this->mentorClient,
            'PATCH',
            '/sessions/'.$session->getId(),
            $this->mentorCsrfToken,
            [
                'json' => [
                    'status' => Session::STATUS_COMPLETED,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        // Le mentor ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        self::assertEquals($notificationsBefore, $notificationsAfter);
    }
}

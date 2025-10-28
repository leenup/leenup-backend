<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\Session;
use App\Factory\CategoryFactory;
use App\Factory\NotificationFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class SessionNotificationTest extends ApiTestCase
{
    use Factories;

    private string $mentorToken;
    private string $studentToken;
    private $mentor;
    private $student;
    private $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Programming']);

        $this->skill = SkillFactory::createOne([
            'title' => 'PHP',
            'category' => $category,
        ]);

        $this->mentor = UserFactory::createOne([
            'email' => 'mentor@test.com',
            'plainPassword' => 'password',
            'firstName' => 'John',
            'lastName' => 'Mentor',
        ]);

        $this->student = UserFactory::createOne([
            'email' => 'student@test.com',
            'plainPassword' => 'password',
            'firstName' => 'Alice',
            'lastName' => 'Student',
        ]);

        // Le mentor doit avoir la skill en "teach"
        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $this->skill,
            'type' => 'teach',
            'level' => 'expert',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'mentor@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->mentorToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'student@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->studentToken = $response->toArray()['token'];
    }

    // ========================================
    // TESTS NOTIFICATION CRÉATION DE SESSION
    // ========================================

    public function testMentorReceivesNotificationWhenSessionIsCreated(): void
    {
        $notificationsBefore = NotificationFactory::count(['user' => $this->mentor]);

        // Student crée une session
        static::createClient()->request('POST', '/sessions', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'mentor' => '/users/' . $this->mentor->getId(),
                'skill' => '/skills/' . $this->skill->getId(),
                'scheduledAt' => (new \DateTimeImmutable('+1 week'))->format('c'),
                'duration' => 60,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Vérifier qu'une notification a été créée pour le mentor
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        // Récupérer la notification
        $notification = NotificationFactory::findBy(
            ['user' => $this->mentor],
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_SESSION_REQUESTED, $notification->getType());
        $this->assertEquals('Nouvelle demande de session', $notification->getTitle());
        $this->assertStringContainsString('Alice Student', $notification->getContent());
        $this->assertStringContainsString('PHP', $notification->getContent());
        $this->assertStringStartsWith('/sessions/', $notification->getLink());
    }

    public function testStudentDoesNotReceiveNotificationWhenCreatingSession(): void
    {
        $notificationsBefore = NotificationFactory::count(['user' => $this->student]);

        // Student crée une session
        static::createClient()->request('POST', '/sessions', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'mentor' => '/users/' . $this->mentor->getId(),
                'skill' => '/skills/' . $this->skill->getId(),
                'scheduledAt' => (new \DateTimeImmutable('+1 week'))->format('c'),
                'duration' => 60,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        // Le student ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        $this->assertEquals($notificationsBefore, $notificationsAfter);
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
        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_CONFIRMED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier qu'une notification a été créée pour le student
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        $notification = NotificationFactory::findBy(
            ['user' => $this->student],
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_SESSION_CONFIRMED, $notification->getType());
        $this->assertEquals('Session confirmée', $notification->getTitle());
        $this->assertStringContainsString('John Mentor', $notification->getContent());
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
        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'status' => Session::STATUS_CANCELLED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Les DEUX doivent avoir reçu une notification
        $mentorNotifsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $studentNotifsAfter = NotificationFactory::count(['user' => $this->student]);

        $this->assertEquals($mentorNotifsBefore + 1, $mentorNotifsAfter);
        $this->assertEquals($studentNotifsBefore + 1, $studentNotifsAfter);

        // Vérifier la notification du mentor - FILTRE PAR TYPE
        $mentorNotif = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_SESSION_CANCELLED], // ← Ajoute le type
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_SESSION_CANCELLED, $mentorNotif->getType());
        $this->assertEquals('Session annulée', $mentorNotif->getTitle());

        // Vérifier la notification du student - FILTRE PAR TYPE
        $studentNotif = NotificationFactory::findBy(
            ['user' => $this->student, 'type' => Notification::TYPE_SESSION_CANCELLED], // ← Ajoute le type
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_SESSION_CANCELLED, $studentNotif->getType());
        $this->assertEquals('Session annulée', $studentNotif->getTitle());
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
        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_COMPLETED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier qu'une notification a été créée pour le student
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        $notification = NotificationFactory::findBy(
            ['user' => $this->student],
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_SESSION_COMPLETED, $notification->getType());
        $this->assertEquals('Session terminée', $notification->getTitle());
        $this->assertStringContainsString('laisser un avis', $notification->getContent());
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
        static::createClient()->request('PATCH', '/sessions/' . $session->getId(), [
            'auth_bearer' => $this->mentorToken,
            'json' => [
                'status' => Session::STATUS_COMPLETED,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Le mentor ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $this->assertEquals($notificationsBefore, $notificationsAfter);
    }
}

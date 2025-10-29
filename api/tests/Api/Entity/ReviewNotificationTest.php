<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\Session;
use App\Factory\CategoryFactory;
use App\Factory\NotificationFactory;
use App\Factory\ReviewFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Test\Factories;

class ReviewNotificationTest extends ApiTestCase
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

        // Générer un suffix unique pour éviter les deadlocks en tests parallèles
        $uniqueId = uniqid();

        $category = CategoryFactory::createOne(['title' => 'Programming']);

        $this->skill = SkillFactory::createOne([
            'title' => 'PHP',
            'category' => $category,
        ]);

        $this->mentor = UserFactory::createOne([
            'email' => "mentor-{$uniqueId}@test.com",
            'plainPassword' => 'password',
            'firstName' => 'John',
            'lastName' => 'Mentor',
        ]);

        $this->student = UserFactory::createOne([
            'email' => "student-{$uniqueId}@test.com",
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
            'json' => ['email' => "mentor-{$uniqueId}@test.com", 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->mentorToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => "student-{$uniqueId}@test.com", 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->studentToken = $response->toArray()['token'];
    }

    // ========================================
    // TESTS NOTIFICATION REVIEW
    // ========================================

    public function testMentorReceivesNotificationWhenReviewIsCreated(): void
    {
        // Créer une session complétée
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->mentor]);

        // Student crée une review
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $session->getId(),
                'rating' => 5,
                'comment' => 'Excellent mentor!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Vérifier qu'une notification a été créée pour le mentor
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        // Récupérer la notification
        $notification = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_NEW_REVIEW],
            ['createdAt' => 'DESC']
        )[0];

        $this->assertEquals(Notification::TYPE_NEW_REVIEW, $notification->getType());
        $this->assertEquals('Nouvel avis reçu', $notification->getTitle());
        $this->assertStringContainsString('Alice Student', $notification->getContent());
        $this->assertStringContainsString('5/5', $notification->getContent());
        $this->assertStringContainsString('PHP', $notification->getContent());
        $this->assertStringStartsWith('/reviews/', $notification->getLink());
    }

    public function testStudentDoesNotReceiveNotificationWhenCreatingReview(): void
    {
        // Créer une session complétée
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->student]);

        // Student crée une review
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $session->getId(),
                'rating' => 5,
                'comment' => 'Great session!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        // Le student ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        $this->assertEquals($notificationsBefore, $notificationsAfter);
    }

    public function testNotificationContainsCorrectRating(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        // Test avec une note de 3/5
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $session->getId(),
                'rating' => 3,
                'comment' => 'Good but could be better',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $notification = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_NEW_REVIEW],
            ['createdAt' => 'DESC']
        )[0];

        $this->assertStringContainsString('3/5', $notification->getContent());
    }

    public function testNotificationIsCreatedEvenWithoutComment(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => new \DateTimeImmutable('-1 day'),
            'duration' => 60,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->mentor]);

        // Review sans commentaire
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $session->getId(),
                'rating' => 4,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // La notification doit quand même être créée
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);
    }
}

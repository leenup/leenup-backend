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

class ReviewNotificationTest extends ApiTestCase
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

        // Création des utilisateurs via le trait (avec cookies + CSRF)
        [
            $this->mentorClient,
            $this->mentorCsrfToken,
            $this->mentor,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-review'),
            password: 'password',
        );

        [
            $this->studentClient,
            $this->studentCsrfToken,
            $this->student,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-review'),
            password: 'password',
        );

        // On force les noms pour coller au contenu attendu des notifications
        $em = static::getContainer()->get('doctrine')->getManager();
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

        // Student crée une review (requête NON sûre → via requestUnsafe)
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$session->getId(),
                    'rating' => 5,
                    'comment' => 'Excellent mentor!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Vérifier qu'une notification a été créée pour le mentor
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);

        // Récupérer la notification
        $notifications = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_NEW_REVIEW],
            ['createdAt' => 'DESC']
        );

        self::assertNotEmpty($notifications, 'Aucune notification de type new_review trouvée pour le mentor.');

        /** @var Notification $notification */
        $notification = $notifications[0];

        self::assertEquals(Notification::TYPE_NEW_REVIEW, $notification->getType());
        self::assertEquals('Nouvel avis reçu', $notification->getTitle());
        self::assertStringContainsString('Alice Student', $notification->getContent());
        self::assertStringContainsString('5/5', $notification->getContent());
        self::assertStringContainsString('PHP', $notification->getContent());
        self::assertStringStartsWith('/reviews/', $notification->getLink());
    }

    public function testStudentDoesNotReceiveNotificationWhenCreatingReview(): void
    {
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
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$session->getId(),
                    'rating' => 5,
                    'comment' => 'Great session!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Le student ne doit PAS recevoir de notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->student]);
        self::assertEquals($notificationsBefore, $notificationsAfter);
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
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$session->getId(),
                    'rating' => 3,
                    'comment' => 'Good but could be better',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $notifications = NotificationFactory::findBy(
            ['user' => $this->mentor, 'type' => Notification::TYPE_NEW_REVIEW],
            ['createdAt' => 'DESC']
        );

        self::assertNotEmpty($notifications);

        /** @var Notification $notification */
        $notification = $notifications[0];

        self::assertStringContainsString('3/5', $notification->getContent());
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
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$session->getId(),
                    'rating' => 4,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // La notification doit quand même être créée
        $notificationsAfter = NotificationFactory::count(['user' => $this->mentor]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);
    }
}

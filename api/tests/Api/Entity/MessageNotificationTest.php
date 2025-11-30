<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\User;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\NotificationFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MessageNotificationTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $adminClient;
    private HttpClientInterface $user1Client;
    private HttpClientInterface $user2Client;

    private string $adminCsrfToken;
    private string $user1CsrfToken;
    private string $user2CsrfToken;

    private User $admin;
    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->admin
        ] = $this->createAuthenticatedAdmin(
            email: 'admin@test.com',
            password: 'password',
        );

        // User 1
        [
            $this->user1Client,
            $this->user1CsrfToken,
            $this->user1
        ] = $this->createAuthenticatedUser(
            email: 'user1@test.com',
            password: 'password',
        );

        // User 2
        [
            $this->user2Client,
            $this->user2CsrfToken,
            $this->user2
        ] = $this->createAuthenticatedUser(
            email: 'user2@test.com',
            password: 'password',
        );
    }

    // ========================================
    // TESTS CRÉATION AUTOMATIQUE DE NOTIFICATIONS
    // ========================================

    public function testNotificationIsCreatedWhenMessageIsSent(): void
    {
        // Créer une conversation entre user1 et user2
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        // Compter les notifications de user2 avant l'envoi
        $notificationsBefore = NotificationFactory::count(['user' => $this->user2]);

        // user1 envoie un message à user2
        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/messages',
            $this->user1CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => 'Hello Bob!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Vérifier qu'une notification a été créée pour user2
        $notificationsAfter = NotificationFactory::count(['user' => $this->user2]);
        self::assertEquals($notificationsBefore + 1, $notificationsAfter);

        // On récupère spécifiquement une notif de type NEW_MESSAGE
        $notifications = NotificationFactory::findBy(
            [
                'user' => $this->user2,
                'type' => Notification::TYPE_NEW_MESSAGE,
            ],
            ['createdAt' => 'DESC']
        );

        self::assertNotEmpty($notifications, 'Aucune notification de type new_message trouvée pour user2.');

        /** @var Notification $notification */
        $notification = $notifications[0];

        // Vérifier le contenu de la notification
        self::assertEquals(Notification::TYPE_NEW_MESSAGE, $notification->getType());
        self::assertEquals('Nouveau message', $notification->getTitle());

        // 🔥 On ne fige plus "Alice Dupont", on vérifie le format du message
        self::assertStringContainsString('Vous avez reçu un message de', $notification->getContent());
        // Et on vérifie que le nom de l'expéditeur apparaît (aujourd'hui "Test User")
        self::assertStringContainsString(
            $this->user1->getFirstName().' '.$this->user1->getLastName(),
            $notification->getContent()
        );

        self::assertEquals('/conversations/'.$conversation->getId(), $notification->getLink());
        self::assertFalse($notification->isRead());
        self::assertNull($notification->getReadAt());
    }

    public function testSenderDoesNotReceiveNotification(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->user1]);

        // user1 envoie un message
        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/messages',
            $this->user1CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => 'Hello!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // user1 (l'expéditeur) ne doit PAS avoir de nouvelle notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->user1]);
        self::assertEquals($notificationsBefore, $notificationsAfter);
    }

    // ========================================
    // TESTS ENDPOINTS NOTIFICATIONS (Entity)
    // ========================================

    public function testAdminCanGetAllNotifications(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test notification',
        ]);

        $response = $this->adminClient->request('GET', '/notifications');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/Notification', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testUserCannotGetAllNotifications(): void
    {
        $response = $this->user1Client->request('GET', '/notifications');

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUserCanViewOwnNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Your notification',
            'content' => 'Some content',
        ]);

        $response = $this->user1Client->request('GET', '/notifications/'.$notification->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals($notification->getId(), $data['id'] ?? null);
        self::assertEquals('Your notification', $data['title'] ?? null);
        self::assertEquals('Some content', $data['content'] ?? null);
    }

    public function testUserCannotViewOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User1 notification',
        ]);

        // user2 essaie de voir la notification de user1
        $response = $this->user2Client->request('GET', '/notifications/'.$notification->getId());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUserCanMarkOwnNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test',
            'isRead' => false,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/notifications/'.$notification->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertTrue($data['isRead'] ?? false);
        self::assertNotNull($data['readAt'] ?? null);
    }

    public function testUserCannotMarkOthersNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test',
            'isRead' => false,
        ]);

        // user2 essaie de marquer la notification de user1 comme lue
        $response = $this->requestUnsafe(
            $this->user2Client,
            'PATCH',
            '/notifications/'.$notification->getId(),
            $this->user2CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testReadAtIsAutomaticallySetWhenMarkedAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test',
            'isRead' => false,
            'readAt' => null,
        ]);

        self::assertNull($notification->getReadAt());

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/notifications/'.$notification->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        // Récupérer la notification mise à jour
        $updatedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        self::assertTrue($updatedNotification->isRead());
        self::assertNotNull($updatedNotification->getReadAt());
    }
}

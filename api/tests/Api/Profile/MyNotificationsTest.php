<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\User;
use App\Factory\NotificationFactory;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MyNotificationsTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $user1Client;
    private HttpClientInterface $user2Client;

    private string $user1CsrfToken;
    private string $user2CsrfToken;

    private User $user1;
    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $uniqueId = uniqid();

        // User 1
        [
            $this->user1Client,
            $this->user1CsrfToken,
            $this->user1,
        ] = $this->createAuthenticatedUser(
            email: "user1-{$uniqueId}@test.com",
            password: 'password',
        );

        // User 2
        [
            $this->user2Client,
            $this->user2CsrfToken,
            $this->user2,
        ] = $this->createAuthenticatedUser(
            email: "user2-{$uniqueId}@test.com",
            password: 'password',
        );
    }

    // ========================================
    // TESTS GET COLLECTION /me/notifications
    // ========================================

    public function testGetMyNotifications(): void
    {
        // Créer des notifications pour user1
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Notification 1',
            'content' => 'Content 1',
            'link' => '/conversations/1',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Notification 2',
            'content' => 'Content 2',
        ]);

        // Créer une notification pour user2 (ne doit pas apparaître)
        NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        $response = $this->user1Client->request('GET', '/me/notifications');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('/contexts/MyNotification', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);

        // User1 doit avoir exactement 2 notifications
        self::assertEquals(2, $data['totalItems']);
        self::assertCount(2, $data['member']);

        $titles = array_column($data['member'], 'title');
        self::assertContains('Notification 1', $titles);
        self::assertContains('Notification 2', $titles);
        self::assertNotContains('User2 notification', $titles);
    }

    public function testGetMyNotificationsWhenEmpty(): void
    {
        $response = $this->user1Client->request('GET', '/me/notifications');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertEquals(0, $data['totalItems']);
        self::assertCount(0, $data['member']);
    }

    public function testGetMyNotificationsWithoutAuth(): void
    {
        $response = static::createClient()->request('GET', '/me/notifications');

        self::assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // TESTS GET ITEM /me/notifications/{id}
    // ========================================

    public function testGetMyNotificationById(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'My notification',
            'content' => 'Some content',
            'link' => '/conversations/1',
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications/'.$notification->getId()
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertEquals($notification->getId(), $data['id'] ?? null);
        self::assertEquals('My notification', $data['title'] ?? null);
        self::assertEquals('Some content', $data['content'] ?? null);
        self::assertEquals('/conversations/1', $data['link'] ?? null);
        self::assertEquals(Notification::TYPE_NEW_MESSAGE, $data['type'] ?? null);
        self::assertFalse($data['isRead'] ?? true);
    }

    public function testCannotGetOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications/'.$notification->getId()
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetNonExistentNotification(): void
    {
        $response = $this->user1Client->request(
            'GET',
            '/me/notifications/99999'
        );

        self::assertSame(404, $response->getStatusCode());
    }

    // ========================================
    // TESTS PATCH /me/notifications/{id}
    // ========================================

    public function testMarkNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test notification',
        ]);

        self::assertFalse($notification->isRead());
        self::assertNull($notification->getReadAt());

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/me/notifications/'.$notification->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertTrue($data['isRead'] ?? false);
        self::assertNotNull($data['readAt'] ?? null);

        $updatedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        self::assertTrue($updatedNotification->isRead());
        self::assertNotNull($updatedNotification->getReadAt());
    }

    public function testMarkNotificationAsUnread(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test notification',
            'isRead' => true,
        ]);

        self::assertTrue($notification->isRead());

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/me/notifications/'.$notification->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => false,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertFalse($data['isRead'] ?? true);
    }

    public function testCannotUpdateOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/me/notifications/'.$notification->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(404, $response->getStatusCode());

        $unchangedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        self::assertFalse($unchangedNotification->isRead());
    }

    public function testPatchNonExistentNotification(): void
    {
        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/me/notifications/99999',
            $this->user1CsrfToken,
            [
                'json' => [
                    'isRead' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testMarkAllNotificationsAsRead(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Notification 1',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Notification 2',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_REVIEW,
            'title' => 'Notification 3',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Already read',
            'isRead' => true,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        $unreadCount = NotificationFactory::count([
            'user' => $this->user1,
            'isRead' => false,
        ]);
        self::assertEquals(3, $unreadCount);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/me/notifications/mark-all-read',
            $this->user1CsrfToken,
            [
                'json' => [],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertEquals(3, $data['count'] ?? null);
        self::assertStringContainsString('3 notification(s) marked as read', $data['message'] ?? '');

        $unreadCountAfter = NotificationFactory::count([
            'user' => $this->user1,
            'isRead' => false,
        ]);
        self::assertEquals(0, $unreadCountAfter);

        $allUser1Notifications = NotificationFactory::findBy(['user' => $this->user1]);
        foreach ($allUser1Notifications as $notif) {
            self::assertTrue($notif->isRead());
        }

        $user2Notification = NotificationFactory::findBy(['user' => $this->user2])[0];
        self::assertFalse($user2Notification->isRead());
    }

    public function testMarkAllAsReadWhenNoUnreadNotifications(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Already read 1',
            'isRead' => true,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Already read 2',
            'isRead' => true,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/me/notifications/mark-all-read',
            $this->user1CsrfToken,
            [
                'json' => [],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertEquals(0, $data['count'] ?? null);
        self::assertStringContainsString('0 notification(s) marked as read', $data['message'] ?? '');
    }

    public function testMarkAllAsReadWithoutAuth(): void
    {
        $response = static::createClient()->request(
            'POST',
            '/me/notifications/mark-all-read',
            [
                'json' => [],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // TESTS FILTRES ET TRI
    // ========================================

    public function testFilterNotificationsByIsRead(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Unread 1',
            'isRead' => false,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Read 1',
            'isRead' => true,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Unread 2',
            'isRead' => false,
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications?isRead=false'
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        self::assertEquals(2, $data['totalItems']);
        foreach ($data['member'] as $notification) {
            self::assertFalse($notification['isRead']);
        }

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications?isRead=true'
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        self::assertEquals(1, $data['totalItems']);
        self::assertTrue($data['member'][0]['isRead']);
    }

    public function testFilterNotificationsByType(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Message notif',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Session notif',
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Another message',
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications?type='.Notification::TYPE_NEW_MESSAGE
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        self::assertEquals(2, $data['totalItems']);
        foreach ($data['member'] as $notification) {
            self::assertEquals(Notification::TYPE_NEW_MESSAGE, $notification['type']);
        }
    }

    public function testOrderNotificationsByCreatedAt(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'First',
        ]);

        sleep(1);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Second',
        ]);

        sleep(1);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Third',
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications'
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        $titles = array_column($data['member'], 'title');

        self::assertEquals('Third', $titles[0]);
        self::assertEquals('Second', $titles[1]);
        self::assertEquals('First', $titles[2]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications?order[createdAt]=asc'
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        $titles = array_column($data['member'], 'title');

        self::assertEquals('First', $titles[0]);
        self::assertEquals('Second', $titles[1]);
        self::assertEquals('Third', $titles[2]);
    }

    public function testCombineFilters(): void
    {
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Unread message',
            'isRead' => false,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Unread session',
            'isRead' => false,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Read message',
            'isRead' => true,
        ]);

        $response = $this->user1Client->request(
            'GET',
            '/me/notifications?isRead=false&type='.Notification::TYPE_NEW_MESSAGE
        );

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        self::assertEquals(1, $data['totalItems']);
        self::assertEquals('Unread message', $data['member'][0]['title']);
        self::assertFalse($data['member'][0]['isRead']);
        self::assertEquals(Notification::TYPE_NEW_MESSAGE, $data['member'][0]['type']);
    }
}

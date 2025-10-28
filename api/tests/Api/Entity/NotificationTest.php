<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\NotificationFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class NotificationTest extends ApiTestCase
{
    use Factories;

    private string $adminToken;
    private string $user1Token;
    private string $user2Token;
    private $admin;
    private $user1;
    private $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserFactory::createOne([
            'email' => 'admin@test.com',
            'plainPassword' => 'password',
            'roles' => ['ROLE_ADMIN'],
        ]);

        $this->user1 = UserFactory::createOne([
            'email' => 'user1@test.com',
            'plainPassword' => 'password',
            'firstName' => 'Alice',
            'lastName' => 'Dupont',
        ]);

        $this->user2 = UserFactory::createOne([
            'email' => 'user2@test.com',
            'plainPassword' => 'password',
            'firstName' => 'Bob',
            'lastName' => 'Martin',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'admin@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->adminToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user1@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->user1Token = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user2@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->user2Token = $response->toArray()['token'];
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
        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'Hello Bob!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        // Vérifier qu'une notification a été créée pour user2
        $notificationsAfter = NotificationFactory::count(['user' => $this->user2]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        // Récupérer la notification créée
        $notification = NotificationFactory::findBy(['user' => $this->user2], ['createdAt' => 'DESC'])[0];

        // Vérifier le contenu de la notification
        $this->assertEquals(Notification::TYPE_NEW_MESSAGE, $notification->getType());
        $this->assertEquals('Nouveau message', $notification->getTitle());
        $this->assertStringContainsString('Alice Dupont', $notification->getContent());
        $this->assertEquals('/conversations/' . $conversation->getId(), $notification->getLink());
        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->getReadAt());
    }

    public function testSenderDoesNotReceiveNotification(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->user1]);

        // user1 envoie un message
        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'Hello!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        // user1 (l'expéditeur) ne doit PAS avoir de nouvelle notification
        $notificationsAfter = NotificationFactory::count(['user' => $this->user1]);
        $this->assertEquals($notificationsBefore, $notificationsAfter);
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

        $response = static::createClient()->request('GET', '/notifications', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Notification',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testUserCannotGetAllNotifications(): void
    {
        static::createClient()->request('GET', '/notifications', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanViewOwnNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Your notification',
            'content' => 'Some content',
        ]);

        $response = static::createClient()->request('GET', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals($notification->getId(), $data['id']);
        $this->assertEquals('Your notification', $data['title']);
        $this->assertEquals('Some content', $data['content']);
    }

    public function testUserCannotViewOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User1 notification',
        ]);

        // user2 essaie de voir la notification de user1
        static::createClient()->request('GET', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user2Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanMarkOwnNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test',
            'isRead' => false,
        ]);

        $response = static::createClient()->request('PATCH', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'isRead' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['isRead']);
        $this->assertNotNull($data['readAt']);
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
        static::createClient()->request('PATCH', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user2Token,
            'json' => [
                'isRead' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
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

        $this->assertNull($notification->getReadAt());

        static::createClient()->request('PATCH', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'isRead' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Récupérer la notification mise à jour
        $updatedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        $this->assertTrue($updatedNotification->isRead());
        $this->assertNotNull($updatedNotification->getReadAt());
    }
}

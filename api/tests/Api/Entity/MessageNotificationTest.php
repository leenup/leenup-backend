<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\NotificationFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class MessageNotificationTest extends ApiTestCase
{
    use Factories;

    private string $adminToken;
    private string $user1Token;
    private string $user2Token;
    private $admin;
    private $user1;
    private $user2;

    /**
     * ✅ setUp() propre et conforme aux best practices
     * Fonctionne maintenant avec ParaTest grâce à database per worker
     */
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

    public function testNotificationIsCreatedWhenMessageIsSent(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $notificationsBefore = NotificationFactory::count(['user' => $this->user2]);

        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'Hello Bob!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $notificationsAfter = NotificationFactory::count(['user' => $this->user2]);
        $this->assertEquals($notificationsBefore + 1, $notificationsAfter);

        $notification = NotificationFactory::findBy(['user' => $this->user2], ['createdAt' => 'DESC'])[0];

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

        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'Hello!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $notificationsAfter = NotificationFactory::count(['user' => $this->user1]);
        $this->assertEquals($notificationsBefore, $notificationsAfter);
    }

    // Autres tests...
}

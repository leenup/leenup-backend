<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Factory\NotificationFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class MyNotificationsTest extends ApiTestCase
{
    use Factories;

    private string $user1Token;
    private string $user2Token;
    private $user1;
    private $user2;

    protected function setUp(): void
    {
        parent::setUp();

        $uniqueId = uniqid();

        $this->user1 = UserFactory::createOne([
            'email' => "user1-{$uniqueId}@test.com",
            'plainPassword' => 'password',
            'firstName' => 'Alice',
            'lastName' => 'User',
        ]);

        $this->user2 = UserFactory::createOne([
            'email' => "user2-{$uniqueId}@test.com",
            'plainPassword' => 'password',
            'firstName' => 'Bob',
            'lastName' => 'User',
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => "user1-{$uniqueId}@test.com", 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->user1Token = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => "user2-{$uniqueId}@test.com", 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->user2Token = $response->toArray()['token'];
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

        $response = static::createClient()->request('GET', '/me/notifications', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/MyNotification',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();

        // User1 doit avoir exactement 2 notifications
        $this->assertEquals(2, $data['totalItems']);
        $this->assertCount(2, $data['member']);

        // Vérifier que les notifications sont triées par date décroissante (plus récentes d'abord)
        $titles = array_column($data['member'], 'title');
        $this->assertContains('Notification 1', $titles);
        $this->assertContains('Notification 2', $titles);
        $this->assertNotContains('User2 notification', $titles);
    }

    public function testGetMyNotificationsWhenEmpty(): void
    {
        $response = static::createClient()->request('GET', '/me/notifications', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(0, $data['totalItems']);
        $this->assertCount(0, $data['member']);
    }

    public function testGetMyNotificationsWithoutAuth(): void
    {
        static::createClient()->request('GET', '/me/notifications');

        $this->assertResponseStatusCodeSame(401);
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

        $response = static::createClient()->request('GET', '/me/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals($notification->getId(), $data['id']);
        $this->assertEquals('My notification', $data['title']);
        $this->assertEquals('Some content', $data['content']);
        $this->assertEquals('/conversations/1', $data['link']);
        $this->assertEquals(Notification::TYPE_NEW_MESSAGE, $data['type']);
        $this->assertFalse($data['isRead']);
        // readAt peut être absent si null, on ne le teste pas ici
    }

    public function testCannotGetOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        // user1 essaie de voir la notification de user2
        static::createClient()->request('GET', '/me/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetNonExistentNotification(): void
    {
        static::createClient()->request('GET', '/me/notifications/99999', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseStatusCodeSame(404);
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

        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->getReadAt());

        $response = static::createClient()->request('PATCH', '/me/notifications/' . $notification->getId(), [
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

        // Vérifier en base de données
        $updatedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        $this->assertTrue($updatedNotification->isRead());
        $this->assertNotNull($updatedNotification->getReadAt());
    }

    public function testMarkNotificationAsUnread(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Test notification',
            'isRead' => true, // ← Créer directement avec isRead = true
        ]);

        $this->assertTrue($notification->isRead());

        // Puis marquer comme non lu
        $response = static::createClient()->request('PATCH', '/me/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'isRead' => false,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertFalse($data['isRead']);
    }

    public function testCannotUpdateOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        // user1 essaie de modifier la notification de user2
        static::createClient()->request('PATCH', '/me/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'isRead' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(404);

        // Vérifier que la notification de user2 n'a pas été modifiée
        $unchangedNotification = NotificationFactory::find(['id' => $notification->getId()]);
        $this->assertFalse($unchangedNotification->isRead());
    }

    public function testPatchNonExistentNotification(): void
    {
        static::createClient()->request('PATCH', '/me/notifications/99999', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'isRead' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}

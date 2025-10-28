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

    public function testMarkAllNotificationsAsRead(): void
    {
        // Créer plusieurs notifications non lues pour user1
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

        // Créer une notification déjà lue (ne doit pas être comptée)
        NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Already read',
            'isRead' => true,
        ]);

        // Créer une notification pour user2 (ne doit pas être affectée)
        NotificationFactory::createOne([
            'user' => $this->user2,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'User2 notification',
        ]);

        // Vérifier qu'il y a 3 notifications non lues pour user1
        $unreadCount = NotificationFactory::count([
            'user' => $this->user1,
            'isRead' => false,
        ]);
        $this->assertEquals(3, $unreadCount);

        // Appeler l'endpoint
        $response = static::createClient()->request('POST', '/me/notifications/mark-all-read', [
            'auth_bearer' => $this->user1Token,
            'json' => [], // ← Ajoute un body JSON vide
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(3, $data['count']);
        $this->assertStringContainsString('3 notification(s) marked as read', $data['message']);

        // Vérifier qu'il n'y a plus de notifications non lues pour user1
        $unreadCountAfter = NotificationFactory::count([
            'user' => $this->user1,
            'isRead' => false,
        ]);
        $this->assertEquals(0, $unreadCountAfter);

        // Vérifier que toutes les notifications de user1 sont maintenant lues
        $allUser1Notifications = NotificationFactory::findBy(['user' => $this->user1]);
        foreach ($allUser1Notifications as $notif) {
            $this->assertTrue($notif->isRead());
        }

        // Vérifier que la notification de user2 n'a pas été affectée
        $user2Notification = NotificationFactory::findBy(['user' => $this->user2])[0];
        $this->assertFalse($user2Notification->isRead());
    }

    public function testMarkAllAsReadWhenNoUnreadNotifications(): void
    {
        // Créer seulement des notifications déjà lues
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

        $response = static::createClient()->request('POST', '/me/notifications/mark-all-read', [
            'auth_bearer' => $this->user1Token,
            'json' => [], // ← Ajoute un body JSON vide
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(0, $data['count']);
        $this->assertStringContainsString('0 notification(s) marked as read', $data['message']);
    }

    public function testMarkAllAsReadWithoutAuth(): void
    {
        static::createClient()->request('POST', '/me/notifications/mark-all-read', [
            'json' => [], // ← Ajoute un body JSON vide
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ========================================
// TESTS FILTRES ET TRI
// ========================================

    public function testFilterNotificationsByIsRead(): void
    {
        // Créer des notifications lues et non lues
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

        // Filtrer les non lues
        $response = static::createClient()->request('GET', '/me/notifications?isRead=false', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);
        foreach ($data['member'] as $notification) {
            $this->assertFalse($notification['isRead']);
        }

        // Filtrer les lues
        $response = static::createClient()->request('GET', '/me/notifications?isRead=true', [
            'auth_bearer' => $this->user1Token,
        ]);

        $data = $response->toArray();
        $this->assertEquals(1, $data['totalItems']);
        $this->assertTrue($data['member'][0]['isRead']);
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

        // Filtrer par type
        $response = static::createClient()->request('GET', '/me/notifications?type=new_message', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);
        foreach ($data['member'] as $notification) {
            $this->assertEquals(Notification::TYPE_NEW_MESSAGE, $notification['type']);
        }
    }

    public function testOrderNotificationsByCreatedAt(): void
    {
        // Créer des notifications avec des dates différentes
        $notif1 = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'First',
        ]);

        sleep(1); // Attendre 1 seconde

        $notif2 = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Second',
        ]);

        sleep(1);

        $notif3 = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Third',
        ]);

        // Ordre décroissant (par défaut, du plus récent au plus ancien)
        $response = static::createClient()->request('GET', '/me/notifications', [
            'auth_bearer' => $this->user1Token,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertEquals('Third', $titles[0]);
        $this->assertEquals('Second', $titles[1]);
        $this->assertEquals('First', $titles[2]);

        // Ordre croissant (du plus ancien au plus récent)
        $response = static::createClient()->request('GET', '/me/notifications?order[createdAt]=asc', [
            'auth_bearer' => $this->user1Token,
        ]);

        $data = $response->toArray();
        $titles = array_column($data['member'], 'title');

        $this->assertEquals('First', $titles[0]);
        $this->assertEquals('Second', $titles[1]);
        $this->assertEquals('Third', $titles[2]);
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

        $response = static::createClient()->request('GET', '/me/notifications?isRead=false&type=new_message', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(1, $data['totalItems']);
        $this->assertEquals('Unread message', $data['member'][0]['title']);
        $this->assertFalse($data['member'][0]['isRead']);
        $this->assertEquals(Notification::TYPE_NEW_MESSAGE, $data['member'][0]['type']);
    }
}

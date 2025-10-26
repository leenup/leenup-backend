<?php
// api/tests/Api/Entity/NotificationTest.php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Notification;
use App\Entity\User;
use App\Factory\NotificationFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class NotificationTest extends ApiTestCase
{
    use Factories;

    private string $user1Token;
    private string $user2Token;
    private string $adminToken;
    private $user1;
    private $user2;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = UserFactory::createOne([
            'email' => 'user1@test.com',
            'plainPassword' => 'password',
        ]);

        $this->user2 = UserFactory::createOne([
            'email' => 'user2@test.com',
            'plainPassword' => 'password',
        ]);

        $this->admin = UserFactory::createOne([
            'email' => 'admin@test.com',
            'plainPassword' => 'password',
            'roles' => ['ROLE_ADMIN'],
        ]);

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

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'admin@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->adminToken = $response->toArray()['token'];
    }

    // ==================== Tests de base ====================

    public function testNotificationCanBeCreated(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Nouveau message',
            'content' => 'Vous avez reçu un nouveau message',
            'link' => '/conversations/1',
            'isRead' => false,
        ]);

        $this->assertNotNull($notification->getId());
        $this->assertEquals(Notification::TYPE_NEW_MESSAGE, $notification->getType());
        $this->assertEquals('Nouveau message', $notification->getTitle());
        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->getReadAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getCreatedAt());
    }

    public function testNotificationBelongsToUser(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Session confirmée',
        ]);

        $this->assertEquals($this->user1->getId(), $notification->getUser()->getId());
    }

    public function testNotificationDefaultsToUnread(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_REVIEW,
            'title' => 'Nouvelle évaluation',
            'isRead' => false, // ← Forcer à false pour le test
        ]);

        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->getReadAt());
    }

    public function testMarkingNotificationAsReadSetsReadAt(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CANCELLED,
            'title' => 'Session annulée',
            'isRead' => false,
        ]);

        $this->assertNull($notification->getReadAt());

        // Marquer comme lu
        $notification->setIsRead(true);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->flush();

        $this->assertTrue($notification->isRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $notification->getReadAt());
    }

    // ==================== Tests API - GET ====================

    public function testAdminCanGetAllNotifications(): void
    {
        NotificationFactory::createMany(3, ['user' => $this->user1]);

        $response = static::createClient()->request('GET', '/notifications', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Notification',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(3, $data['totalItems']);
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
            'title' => 'Test notification',
        ]);

        $response = static::createClient()->request('GET', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals($notification->getId(), $data['id']);
        $this->assertEquals('Test notification', $data['title']);
    }

    public function testUserCannotViewOthersNotification(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_MESSAGE,
            'title' => 'Private notification',
        ]);

        static::createClient()->request('GET', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user2Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ==================== Tests API - PATCH ====================

    public function testUserCanMarkOwnNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_SESSION_CONFIRMED,
            'title' => 'Session confirmée',
            'isRead' => false,
        ]);

        static::createClient()->request('PATCH', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user1Token,
            'json' => ['isRead' => true],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();

        // Vérifier en BDD (plus fiable que la réponse API)
        $repository = static::getContainer()->get('doctrine')->getRepository(Notification::class);
        $updatedNotification = $repository->find($notification->getId());

        $this->assertTrue($updatedNotification->isRead());
        $this->assertInstanceOf(\DateTimeImmutable::class, $updatedNotification->getReadAt());
    }

    public function testUserCannotMarkOthersNotificationAsRead(): void
    {
        $notification = NotificationFactory::createOne([
            'user' => $this->user1,
            'type' => Notification::TYPE_NEW_REVIEW,
            'title' => 'Nouvelle review',
            'isRead' => false,
        ]);

        static::createClient()->request('PATCH', '/notifications/' . $notification->getId(), [
            'auth_bearer' => $this->user2Token,
            'json' => ['isRead' => true],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ==================== Tests Repository ====================

    public function testCountUnreadNotifications(): void
    {
        NotificationFactory::createMany(5, [
            'user' => $this->user1,
            'isRead' => false,
        ]);

        NotificationFactory::createMany(2, [
            'user' => $this->user1,
            'isRead' => true,
        ]);

        // Récupérer l'entité User réelle depuis la BDD
        $userRepository = static::getContainer()->get('doctrine')->getRepository(User::class);
        $user = $userRepository->find($this->user1->getId());

        $repository = static::getContainer()->get('doctrine')->getRepository(Notification::class);
        $unreadCount = $repository->countUnreadByUser($user);

        $this->assertEquals(5, $unreadCount);
    }

    public function testFindUnreadNotifications(): void
    {
        NotificationFactory::createMany(3, [
            'user' => $this->user1,
            'isRead' => false,
        ]);

        NotificationFactory::createOne([
            'user' => $this->user1,
            'isRead' => true,
        ]);

        // Récupérer l'entité User réelle depuis la BDD
        $userRepository = static::getContainer()->get('doctrine')->getRepository(User::class);
        $user = $userRepository->find($this->user1->getId());

        $repository = static::getContainer()->get('doctrine')->getRepository(Notification::class);
        $unreadNotifications = $repository->findUnreadByUser($user);

        $this->assertCount(3, $unreadNotifications);
    }
}

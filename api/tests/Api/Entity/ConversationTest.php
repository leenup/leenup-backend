<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Test\Factories;

class ConversationTest extends ApiTestCase
{
    use Factories;

    private string $adminToken;
    private string $user1Token;
    private string $user2Token;
    private string $user3Token;
    private $admin;
    private $user1;
    private $user2;
    private $user3;

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
        ]);

        $this->user2 = UserFactory::createOne([
            'email' => 'user2@test.com',
            'plainPassword' => 'password',
        ]);

        $this->user3 = UserFactory::createOne([
            'email' => 'user3@test.com',
            'plainPassword' => 'password',
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

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'user3@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->user3Token = $response->toArray()['token'];
    }

    // ========================================
    // TESTS CONVERSATION
    // ========================================

    public function testAdminCanGetAllConversations(): void
    {
        ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = static::createClient()->request('GET', '/conversations', [
            'auth_bearer' => $this->adminToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Conversation',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testUserCannotGetAllConversations(): void
    {
        static::createClient()->request('GET', '/conversations', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUserCanCreateConversation(): void
    {
        $response = static::createClient()->request('POST', '/conversations', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'participant2' => '/users/' . $this->user2->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('Conversation', $data['@type']);
        $this->assertEquals('/users/' . $this->user1->getId(), $data['participant1']);
        $this->assertEquals('/users/' . $this->user2->getId(), $data['participant2']);
        // lastMessageAt peut être null ou absent, on ne teste pas
    }

    public function testCannotCreateConversationWithSelf(): void
    {
        static::createClient()->request('POST', '/conversations', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'participant2' => '/users/' . $this->user1->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'participant2',
                    'message' => 'You cannot create a conversation with yourself',
                ],
            ],
        ]);
    }

    public function testCannotCreateDuplicateConversation(): void
    {
        ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        static::createClient()->request('POST', '/conversations', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'participant2' => '/users/' . $this->user2->getId(),
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'participant2',
                    'message' => 'A conversation already exists with this user',
                ],
            ],
        ]);
    }

    public function testParticipantCanViewConversation(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = static::createClient()->request('GET', '/conversations/' . $conversation->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals($conversation->getId(), $data['id']);
        $this->assertEquals('/users/' . $this->user1->getId(), $data['participant1']);
        $this->assertEquals('/users/' . $this->user2->getId(), $data['participant2']);
    }

    public function testNonParticipantCannotViewConversation(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        static::createClient()->request('GET', '/conversations/' . $conversation->getId(), [
            'auth_bearer' => $this->user3Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetMyConversations(): void
    {
        ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        MessageFactory::createOne([
            'conversation' => ConversationFactory::first(),
            'sender' => $this->user1,
            'content' => 'Hello!',
            'read' => false,
        ]);

        $response = static::createClient()->request('GET', '/me/conversations', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/MyConversation',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
        $this->assertEquals('Hello!', $data['member'][0]['lastMessage']);
        $this->assertEquals('/users/' . $this->user2->getId(), $data['member'][0]['otherParticipant']);
    }

    // ========================================
    // TESTS MESSAGE
    // ========================================

    public function testParticipantCanSendMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'Hello from user1!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();

        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('Message', $data['@type']);
        $this->assertEquals('Hello from user1!', $data['content']);
        $this->assertEquals('/users/' . $this->user1->getId(), $data['sender']);
        $this->assertFalse($data['read']);
    }

    public function testNonParticipantCannotSendMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user3Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'I should not be able to send this!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testMessageContentCannotBeEmpty(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => '',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
        ]);
    }

    public function testMessageUpdatesLastMessageAt(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
            'lastMessageAt' => null,
        ]);

        $this->assertNull($conversation->getLastMessageAt());

        static::createClient()->request('POST', '/messages', [
            'auth_bearer' => $this->user1Token,
            'json' => [
                'conversation' => '/conversations/' . $conversation->getId(),
                'content' => 'This should update lastMessageAt',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        // Récupérer la conversation depuis la BDD pour avoir la valeur à jour
        $updatedConversation = ConversationFactory::find(['id' => $conversation->getId()]);
        $this->assertNotNull($updatedConversation->getLastMessageAt());
    }

    public function testParticipantCanViewMessages(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Message 1',
        ]);

        MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user2,
            'content' => 'Message 2',
        ]);

        $response = static::createClient()->request('GET', '/conversations/' . $conversation->getId() . '/messages', [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(2, $data['totalItems']);
        $this->assertCount(2, $data['member']);
    }

    public function testNonParticipantCannotViewMessages(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        static::createClient()->request('GET', '/conversations/' . $conversation->getId() . '/messages', [
            'auth_bearer' => $this->user3Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testParticipantCanMarkMessageAsRead(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Test message',
            'read' => false,
        ]);

        $response = static::createClient()->request('PATCH', '/messages/' . $message->getId(), [
            'auth_bearer' => $this->user2Token,
            'json' => [
                'read' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertTrue($data['read']);
    }

    public function testNonParticipantCannotMarkMessageAsRead(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Test message',
            'read' => false,
        ]);

        static::createClient()->request('PATCH', '/messages/' . $message->getId(), [
            'auth_bearer' => $this->user3Token,
            'json' => [
                'read' => true,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testParticipantCanViewSingleMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Single message test',
        ]);

        $response = static::createClient()->request('GET', '/messages/' . $message->getId(), [
            'auth_bearer' => $this->user1Token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals($message->getId(), $data['id']);
        $this->assertEquals('Single message test', $data['content']);
    }

    public function testNonParticipantCannotViewSingleMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Secret message',
        ]);

        static::createClient()->request('GET', '/messages/' . $message->getId(), [
            'auth_bearer' => $this->user3Token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}

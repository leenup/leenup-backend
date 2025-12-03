<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class ConversationTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $adminClient;
    private HttpClientInterface $user1Client;
    private HttpClientInterface $user2Client;
    private HttpClientInterface $user3Client;

    private string $adminCsrfToken;
    private string $user1CsrfToken;
    private string $user2CsrfToken;
    private string $user3CsrfToken;

    private User $admin;
    private User $user1;
    private User $user2;
    private User $user3;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin
        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->admin,
        ] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin'),
            password: 'password',
        );

        // User 1
        [
            $this->user1Client,
            $this->user1CsrfToken,
            $this->user1,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user1'),
            password: 'password',
        );

        // User 2
        [
            $this->user2Client,
            $this->user2CsrfToken,
            $this->user2,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user2'),
            password: 'password',
        );

        // User 3
        [
            $this->user3Client,
            $this->user3CsrfToken,
            $this->user3,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user3'),
            password: 'password',
        );
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

        $response = $this->adminClient->request('GET', '/conversations');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/Conversation', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testUserCannotGetAllConversations(): void
    {
        $response = $this->user1Client->request('GET', '/conversations');

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUserCanCreateConversation(): void
    {
        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/conversations',
            $this->user1CsrfToken,
            [
                'json' => [
                    'participant2' => '/users/'.$this->user2->getId(),
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertArrayHasKey('@id', $data);
        self::assertArrayHasKey('@type', $data);
        self::assertSame('Conversation', $data['@type']);
        self::assertSame('/users/'.$this->user1->getId(), $data['participant1']);
        self::assertSame('/users/'.$this->user2->getId(), $data['participant2']);
    }

    public function testCannotCreateConversationWithSelf(): void
    {
        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/conversations',
            $this->user1CsrfToken,
            [
                'json' => [
                    'participant2' => '/users/'.$this->user1->getId(),
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        // Important: ne pas lever d'exception sur 422
        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('participant2', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'You cannot create a conversation with yourself',
            $violations[0]['message'] ?? null
        );
    }

    public function testCannotCreateDuplicateConversation(): void
    {
        ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/conversations',
            $this->user1CsrfToken,
            [
                'json' => [
                    'participant2' => '/users/'.$this->user2->getId(),
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);
        self::assertSame('participant2', $violations[0]['propertyPath'] ?? null);
        self::assertSame(
            'A conversation already exists with this user',
            $violations[0]['message'] ?? null
        );
    }

    public function testParticipantCanViewConversation(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->user1Client->request('GET', '/conversations/'.$conversation->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame($conversation->getId(), $data['id'] ?? null);
        self::assertSame('/users/'.$this->user1->getId(), $data['participant1'] ?? null);
        self::assertSame('/users/'.$this->user2->getId(), $data['participant2'] ?? null);
    }

    public function testNonParticipantCannotViewConversation(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->user3Client->request('GET', '/conversations/'.$conversation->getId());

        self::assertSame(403, $response->getStatusCode());
    }

    public function testGetMyConversations(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Hello!',
            'read' => false,
        ]);

        $response = $this->user1Client->request('GET', '/me/conversations');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/MyConversation', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);

        $first = $data['member'][0] ?? null;
        self::assertNotNull($first);
        self::assertSame('Hello!', $first['lastMessage'] ?? null);
        self::assertSame('/users/'.$this->user2->getId(), $first['otherParticipant'] ?? null);
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

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/messages',
            $this->user1CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => 'Hello from user1!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Message', $data['@type'] ?? null);
        self::assertSame('Hello from user1!', $data['content'] ?? null);
        self::assertSame('/users/'.$this->user1->getId(), $data['sender'] ?? null);
        self::assertFalse($data['read'] ?? true);
    }

    public function testNonParticipantCannotSendMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->requestUnsafe(
            $this->user3Client,
            'POST',
            '/messages',
            $this->user3CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => 'I should not be able to send this!',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testMessageContentCannotBeEmpty(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/messages',
            $this->user1CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => '',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);
    }

    public function testMessageUpdatesLastMessageAt(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
            'lastMessageAt' => null,
        ]);

        self::assertNull($conversation->getLastMessageAt());

        $response = $this->requestUnsafe(
            $this->user1Client,
            'POST',
            '/messages',
            $this->user1CsrfToken,
            [
                'json' => [
                    'conversation' => '/conversations/'.$conversation->getId(),
                    'content' => 'This should update lastMessageAt',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $updatedConversation = ConversationFactory::find(['id' => $conversation->getId()]);
        self::assertNotNull($updatedConversation->getLastMessageAt());
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

        $response = $this->user1Client->request(
            'GET',
            '/conversations/'.$conversation->getId().'/messages'
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertEquals(2, $data['totalItems'] ?? null);
        self::assertCount(2, $data['member'] ?? []);
    }

    public function testNonParticipantCannotViewMessages(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->user3Client->request(
            'GET',
            '/conversations/'.$conversation->getId().'/messages'
        );

        self::assertSame(403, $response->getStatusCode());
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

        $response = $this->requestUnsafe(
            $this->user2Client,
            'PATCH',
            '/messages/'.$message->getId(),
            $this->user2CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertTrue($data['read'] ?? false);
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

        $response = $this->requestUnsafe(
            $this->user3Client,
            'PATCH',
            '/messages/'.$message->getId(),
            $this->user3CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
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

        $response = $this->user1Client->request('GET', '/messages/'.$message->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame($message->getId(), $data['id'] ?? null);
        self::assertSame('Single message test', $data['content'] ?? null);
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

        $response = $this->user3Client->request('GET', '/messages/'.$message->getId());

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS MESSAGE VOTER - PERMISSIONS
    // ========================================

    /**
     * Test qu'on ne peut PAS modifier un message
     * Le MessageVoter interdit l'UPDATE par défaut
     */
    public function testCannotUpdateMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Original message',
            'read' => false,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/messages/'.$message->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'content' => 'Modified message',
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * Test que l'expéditeur peut supprimer son propre message
     */
    public function testSenderCanDeleteOwnMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Message to delete',
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'DELETE',
            '/messages/'.$message->getId(),
            $this->user1CsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $response = $this->user1Client->request('GET', '/messages/'.$message->getId());
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * Test qu'on ne peut PAS supprimer le message de quelqu'un d'autre
     */
    public function testSenderCannotDeleteOthersMessage(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Message from user1',
        ]);

        $response = $this->requestUnsafe(
            $this->user2Client,
            'DELETE',
            '/messages/'.$message->getId(),
            $this->user2CsrfToken
        );

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * Test CRITIQUE : L'expéditeur ne peut PAS marquer son propre message comme lu
     * Seul le DESTINATAIRE peut marquer comme lu
     */
    public function testSenderCannotMarkOwnMessageAsRead(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $message = MessageFactory::createOne([
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Message from user1',
            'read' => false,
        ]);

        $response = $this->requestUnsafe(
            $this->user1Client,
            'PATCH',
            '/messages/'.$message->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $response = $this->user1Client->request('GET', '/messages/'.$message->getId());
        $data = $response->toArray();

        self::assertFalse($data['read'] ?? true, 'Le message ne devrait pas être marqué comme lu');
    }
}

<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

final class MessageTest extends ApiTestCase
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

        [
            $this->adminClient,
            $this->adminCsrfToken,
            $this->admin,
        ] = $this->createAuthenticatedAdmin(
            email: $this->uniqueEmail('admin'),
            password: 'password',
        );

        [
            $this->user1Client,
            $this->user1CsrfToken,
            $this->user1,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user1'),
            password: 'password',
        );

        [
            $this->user2Client,
            $this->user2CsrfToken,
            $this->user2,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('user2'),
            password: 'password',
        );

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
    // TESTS MESSAGE - COLLECTIONS
    // ========================================

    public function testAdminCanGetAllMessagesCollection(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        MessageFactory::createMany(2, [
            'conversation' => $conversation,
            'sender' => $this->user1,
            'content' => 'Hello!',
        ]);

        $response = $this->adminClient->request('GET', '/messages');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(2, $data['totalItems']);
    }

    public function testNonAdminCannotGetAllMessagesCollection(): void
    {
        $response = $this->user1Client->request('GET', '/messages');
        self::assertSame(403, $response->getStatusCode());
    }

    public function testParticipantCanGetConversationMessages(): void
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
            '/conversations/' . $conversation->getId() . '/messages'
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertSame(2, $data['totalItems'] ?? null);
        self::assertCount(2, $data['member'] ?? []);
    }

    public function testNonParticipantCannotGetConversationMessages(): void
    {
        $conversation = ConversationFactory::createOne([
            'participant1' => $this->user1,
            'participant2' => $this->user2,
        ]);

        $response = $this->user3Client->request(
            'GET',
            '/conversations/' . $conversation->getId() . '/messages'
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS MESSAGE - CREATE
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
                    'conversation' => '/conversations/' . $conversation->getId(),
                    'content' => 'Hello from user1!',
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('Message', $data['@type'] ?? null);
        self::assertSame('Hello from user1!', $data['content'] ?? null);
        self::assertSame('/users/' . $this->user1->getId(), $data['sender'] ?? null);
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
                    'conversation' => '/conversations/' . $conversation->getId(),
                    'content' => 'I should not be able to send this!',
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
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
                    'conversation' => '/conversations/' . $conversation->getId(),
                    'content' => '',
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);
    }

    // ========================================
    // TESTS MESSAGE - VIEW SINGLE
    // ========================================

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

        $response = $this->user1Client->request('GET', '/messages/' . $message->getId());

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

        $response = $this->user3Client->request('GET', '/messages/' . $message->getId());

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS MESSAGE - PATCH (mark read / content)
    // ========================================

    public function testRecipientCanMarkMessageAsRead(): void
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
            '/messages/' . $message->getId(),
            $this->user2CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertTrue($data['read'] ?? false);
    }

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
            '/messages/' . $message->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
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
            '/messages/' . $message->getId(),
            $this->user3CsrfToken,
            [
                'json' => [
                    'read' => true,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCannotUpdateMessageContent(): void
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
            '/messages/' . $message->getId(),
            $this->user1CsrfToken,
            [
                'json' => [
                    'content' => 'Modified message',
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS MESSAGE - DELETE
    // ========================================

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
            '/messages/' . $message->getId(),
            $this->user1CsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $response = $this->user1Client->request('GET', '/messages/' . $message->getId());
        self::assertSame(404, $response->getStatusCode());
    }

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
            '/messages/' . $message->getId(),
            $this->user2CsrfToken
        );

        self::assertSame(403, $response->getStatusCode());
    }
}

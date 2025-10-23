<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyConversations;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MyConversations>
 */
final class MyConversationsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ConversationRepository $conversationRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $conversations = $this->conversationRepository->findUserConversations($user);

        return array_map(fn(Conversation $conversation) => $this->mapToDto($conversation, $user), $conversations);
    }

    private function mapToDto(Conversation $conversation, User $currentUser): MyConversations
    {
        $dto = new MyConversations();
        $dto->id = $conversation->getId();

        // Déterminer l'autre participant
        $dto->otherParticipant = $conversation->getParticipant1() === $currentUser
            ? $conversation->getParticipant2()
            : $conversation->getParticipant1();

        // Dernier message
        $messages = $conversation->getMessages()->toArray();
        if (count($messages) > 0) {
            usort($messages, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());
            $lastMessage = $messages[0];
            $dto->lastMessage = $lastMessage->getContent();
        }

        $dto->lastMessageAt = $conversation->getLastMessageAt();

        // Compter les messages non lus (envoyés par l'autre participant)
        $unreadCount = 0;
        foreach ($messages as $message) {
            if ($message->getSender() !== $currentUser && !$message->isRead()) {
                $unreadCount++;
            }
        }
        $dto->unreadCount = $unreadCount;

        $dto->createdAt = $conversation->getCreatedAt();

        return $dto;
    }
}

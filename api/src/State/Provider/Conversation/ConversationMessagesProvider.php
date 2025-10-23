<?php

namespace App\State\Provider\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<Message>
 */
final class ConversationMessagesProvider implements ProviderInterface
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private Security $security,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $conversationId = $uriVariables['conversationId'] ?? null;

        if (!$conversationId) {
            throw new \LogicException('Conversation ID is required');
        }

        $conversation = $this->conversationRepository->find($conversationId);

        if (!$conversation instanceof Conversation) {
            throw new \LogicException('Conversation not found');
        }

        // Vérifier que l'user est participant (sauf si admin)
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            if ($conversation->getParticipant1() !== $user && $conversation->getParticipant2() !== $user) {
                throw new AccessDeniedHttpException('You can only view messages from your own conversations');
            }
        }

        return $conversation->getMessages()->toArray();
    }
}

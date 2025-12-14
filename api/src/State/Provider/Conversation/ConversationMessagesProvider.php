<?php

namespace App\State\Provider\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Security\Voter\ConversationVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<Message>
 */
final class ConversationMessagesProvider implements ProviderInterface
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $conversationId = $uriVariables['conversationId'] ?? null;
        if ($conversationId === null) {
            throw new \LogicException('Conversation ID is required');
        }

        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new NotFoundHttpException('Conversation not found');
        }

        if (!$this->authorizationChecker->isGranted(
            ConversationVoter::VIEW_MESSAGES,
            $conversation
        )) {
            throw new AccessDeniedException(
                'You can only view messages from your own conversations'
            );
        }

        return $conversation->getMessages()->toArray();
    }
}

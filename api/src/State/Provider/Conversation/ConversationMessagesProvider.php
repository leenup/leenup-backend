<?php

namespace App\State\Provider\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ConversationRepository;
use App\Security\Voter\ConversationVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @implements ProviderInterface<Message>
 */
final class ConversationMessagesProvider implements ProviderInterface
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private Security $security,
        private AuthorizationCheckerInterface $authChecker,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required to view conversation messages');
        }

        $conversationId = $uriVariables['conversationId'] ?? null;

        if (!$conversationId) {
            throw new NotFoundHttpException('Conversation ID is required');
        }

        $conversation = $this->conversationRepository->find($conversationId);

        if (!$conversation instanceof Conversation) {
            throw new NotFoundHttpException('Conversation not found');
        }

        // ✅ Utiliser le Voter pour vérifier l'accès aux messages
        if (!$this->authChecker->isGranted(ConversationVoter::VIEW_MESSAGES, $conversation)) {
            throw new AccessDeniedHttpException(
                'You can only view messages from your own conversations'
            );
        }

        return $conversation->getMessages()->toArray();
    }
}

<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ConversationVoter extends Voter
{
    public const VIEW = 'CONVERSATION_VIEW';
    public const CREATE = 'CONVERSATION_CREATE';
    public const DELETE = 'CONVERSATION_DELETE';
    public const VIEW_MESSAGES = 'CONVERSATION_VIEW_MESSAGES';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Conversation) {
            return false;
        }

        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::DELETE,
            self::VIEW_MESSAGES,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Conversation $conversation */
        $conversation = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($conversation, $user),
            self::CREATE => $this->canCreate($conversation, $user),
            self::DELETE => $this->canDelete($conversation, $user),
            self::VIEW_MESSAGES => $this->canViewMessages($conversation, $user),
            default => false,
        };
    }

    private function canView(Conversation $conversation, User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $this->isParticipant($conversation, $user);
    }

    private function canCreate(Conversation $conversation, User $user): bool
    {
        return true;
    }

    private function canDelete(Conversation $conversation, User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function canViewMessages(Conversation $conversation, User $user): bool
    {
        return $this->isParticipant($conversation, $user)
            || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    private function isParticipant(Conversation $conversation, User $user): bool
    {
        $userId = $user->getId();

        if ($userId === null) {
            return false;
        }

        return $conversation->getParticipant1()?->getId() === $userId
            || $conversation->getParticipant2()?->getId() === $userId;
    }
}

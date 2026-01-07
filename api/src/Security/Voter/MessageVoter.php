<?php

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les messages
 */
class MessageVoter extends Voter
{
    public const VIEW = 'MESSAGE_VIEW';
    public const CREATE = 'MESSAGE_CREATE';
    public const UPDATE = 'MESSAGE_UPDATE';
    public const DELETE = 'MESSAGE_DELETE';
    public const MARK_READ = 'MESSAGE_MARK_READ';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Message) {
            return false;
        }

        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::MARK_READ,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Message $message */
        $message = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($message, $user),
            self::CREATE => $this->canCreate($message, $user),
            self::UPDATE => $this->canUpdate($message, $user),
            self::DELETE => $this->canDelete($message, $user),
            self::MARK_READ => $this->canMarkAsRead($message, $user),
            default => false,
        };
    }

    private function canView(Message $message, User $user): bool
    {
        return $this->isParticipant($message, $user);
    }

    private function canCreate(Message $message, User $user): bool
    {
        return $this->isParticipant($message, $user);
    }

    private function canUpdate(Message $message, User $user): bool
    {
        return false;
    }

    private function canDelete(Message $message, User $user): bool
    {
        return $message->getSender()?->getId() === $user->getId();
    }

    private function canMarkAsRead(Message $message, User $user): bool
    {
        if (!$this->isParticipant($message, $user)) {
            return false;
        }

        return $message->getSender()?->getId() !== $user->getId();
    }

    private function isParticipant(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();
        $userId = $user->getId();

        if ($conversation === null || $userId === null) {
            return false;
        }

        return $conversation->getParticipant1()?->getId() === $userId
            || $conversation->getParticipant2()?->getId() === $userId;
    }
}

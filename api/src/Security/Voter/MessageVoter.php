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
        ]);
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

    /**
     * Peut voir le message ?
     * → Tu dois être participant de la conversation
     */
    private function canView(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();

        return $conversation->getParticipant1() === $user
            || $conversation->getParticipant2() === $user;
    }

    /**
     * Peut créer un message dans cette conversation ?
     * → Tu dois être participant de la conversation
     */
    private function canCreate(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();

        return $conversation->getParticipant1() === $user
            || $conversation->getParticipant2() === $user;
    }

    /**
     * Peut modifier le message ?
     * → Dans un système de messagerie, on ne modifie généralement pas les messages
     * → Si tu veux permettre : return $message->getSender() === $user;
     */
    private function canUpdate(Message $message, User $user): bool
    {
        // Option stricte : personne ne peut modifier
        return false;

        // Option souple : l'expéditeur peut modifier
        // return $message->getSender() === $user;
    }

    /**
     * Peut supprimer le message ?
     * → Seul l'expéditeur peut supprimer son message
     */
    private function canDelete(Message $message, User $user): bool
    {
        return $message->getSender() === $user;
    }

    /**
     * Peut marquer le message comme lu ?
     * → Seul le DESTINATAIRE peut marquer comme lu (pas l'expéditeur)
     */
    private function canMarkAsRead(Message $message, User $user): bool
    {
        $conversation = $message->getConversation();
        $sender = $message->getSender();

        $isParticipant = $conversation->getParticipant1() === $user
            || $conversation->getParticipant2() === $user;

        $isNotSender = $sender !== $user;

        return $isParticipant && $isNotSender;
    }
}

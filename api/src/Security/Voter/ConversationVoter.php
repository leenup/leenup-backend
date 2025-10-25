<?php

namespace App\Security\Voter;

use App\Entity\Conversation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les conversations
 */
class ConversationVoter extends Voter
{
    // Constantes pour les permissions
    public const VIEW = 'CONVERSATION_VIEW';
    public const CREATE = 'CONVERSATION_CREATE';
    public const DELETE = 'CONVERSATION_DELETE';
    public const VIEW_MESSAGES = 'CONVERSATION_VIEW_MESSAGES';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Ce voter ne s'applique que sur les objets Conversation
        if (!$subject instanceof Conversation) {
            return false;
        }

        // Et uniquement pour les permissions qu'on gère
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::DELETE,
            self::VIEW_MESSAGES,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var Conversation $conversation */
        $conversation = $subject;

        // Déléguer la vérification selon la permission demandée
        return match ($attribute) {
            self::VIEW => $this->canView($conversation, $user),
            self::CREATE => $this->canCreate($conversation, $user),
            self::DELETE => $this->canDelete($conversation, $user),
            self::VIEW_MESSAGES => $this->canViewMessages($conversation, $user),
            default => false,
        };
    }

    /**
     * Peut voir la conversation ?
     * → Les admins peuvent tout voir
     * → OU tu dois être participant (participant1 ou participant2)
     */
    private function canView(Conversation $conversation, User $user): bool
    {
        // Les admins peuvent tout voir
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Sinon, tu dois être participant
        return $conversation->getParticipant1() === $user
            || $conversation->getParticipant2() === $user;
    }

    /**
     * Peut créer une conversation ?
     * → Tout utilisateur authentifié peut créer une conversation
     * → Les validations métier (pas avec soi-même, pas de doublon) sont dans le Processor
     */
    private function canCreate(Conversation $conversation, User $user): bool
    {
        // Tout utilisateur authentifié peut créer une conversation
        // Les règles métier spécifiques sont dans ConversationCreateProcessor
        return true;
    }

    /**
     * Peut supprimer la conversation ?
     * → Seuls les admins peuvent supprimer
     */
    private function canDelete(Conversation $conversation, User $user): bool
    {
        // Seuls les admins peuvent supprimer des conversations
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Peut voir les messages de cette conversation ?
     * → Tu dois être participant (utilisé par ConversationMessagesProvider)
     */
    private function canViewMessages(Conversation $conversation, User $user): bool
    {
        // Tu dois être participant pour voir les messages
        return $conversation->getParticipant1() === $user
            || $conversation->getParticipant2() === $user;
    }
}

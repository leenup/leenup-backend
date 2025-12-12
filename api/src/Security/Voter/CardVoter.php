<?php

namespace App\Security\Voter;

use App\Entity\Card;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les cartes (Card)
 */
class CardVoter extends Voter
{
    public const VIEW = 'CARD_VIEW';
    public const CREATE = 'CARD_CREATE';
    public const UPDATE = 'CARD_UPDATE';
    public const DELETE = 'CARD_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Card) {
            return false;
        }

        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Card $card */
        $card = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($card, $user),
            self::CREATE => $this->canCreate($user),
            self::UPDATE => $this->canUpdate($user),
            self::DELETE => $this->canDelete($user),
            default => false,
        };
    }

    /**
     * Peut voir la carte ?
     *  - Admin : tout
     *  - Sinon : uniquement les cartes actives
     */
    private function canView(Card $card, User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $card->isActive();
    }

    /**
     * Peut créer une carte ?
     *  - Uniquement admin
     */
    private function canCreate(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Peut modifier une carte ?
     *  - Uniquement admin
     */
    private function canUpdate(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Peut supprimer une carte ?
     *  - Uniquement admin
     */
    private function canDelete(User $user): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }
}

<?php

namespace App\Security\Voter;

use App\Entity\Card;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les cards
 */
class CardVoter extends Voter
{
    // Constantes pour les permissions
    public const VIEW = 'CARD_VIEW';
    public const CREATE = 'CARD_CREATE';
    public const UPDATE = 'CARD_UPDATE';
    public const DELETE = 'CARD_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Ce voter ne s'applique que sur les objets Card
        if (!$subject instanceof Card) {
            return false;
        }

        // Et uniquement pour les permissions qu'on gère
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        /** @var Card $card */
        $card = $subject;

        // Déléguer la vérification selon la permission demandée
        return match ($attribute) {
            self::VIEW => $this->canView($card, $user),
            self::CREATE => $this->canCreate($card, $user),
            self::UPDATE => $this->canUpdate($card, $user),
            self::DELETE => $this->canDelete($card, $user),
            default => false,
        };
    }

    /**
     * Peut voir la card ?
     * → Tout le monde peut voir les cards actives
     * → Les admins peuvent voir même les cards inactives
     */
    private function canView(Card $card, User $user): bool
    {
        // Si la card est active, tout le monde peut la voir
        if ($card->isActive()) {
            return true;
        }

        // Si la card est inactive, seuls les admins peuvent la voir
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Peut créer une card ?
     * → Seuls les admins peuvent créer
     */
    private function canCreate(Card $card, User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Peut modifier la card ?
     * → Seuls les admins peuvent modifier
     */
    private function canUpdate(Card $card, User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }

    /**
     * Peut supprimer la card ?
     * → Seuls les admins peuvent supprimer
     */
    private function canDelete(Card $card, User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles());
    }
}

<?php

namespace App\Security\Voter;

use App\Entity\Review;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les reviews
 */
class ReviewVoter extends Voter
{
    // Constantes pour les permissions
    public const VIEW = 'REVIEW_VIEW';
    public const UPDATE = 'REVIEW_UPDATE';
    public const DELETE = 'REVIEW_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Ce voter ne s'applique que sur les objets Review
        if (!$subject instanceof Review) {
            return false;
        }

        // Et uniquement pour les permissions qu'on gère
        return in_array($attribute, [
            self::VIEW,
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

        /** @var Review $review */
        $review = $subject;

        // Déléguer la vérification selon la permission demandée
        return match ($attribute) {
            self::VIEW => $this->canView($review, $user),
            self::UPDATE => $this->canUpdate($review, $user),
            self::DELETE => $this->canDelete($review, $user),
            default => false,
        };
    }

    /**
     * Peut voir la review ?
     * → Tout le monde peut voir (c'est public)
     */
    private function canView(Review $review, User $user): bool
    {
        // Les reviews sont publiques
        return true;
    }

    /**
     * Peut modifier la review ?
     * → Les admins peuvent toujours modifier
     * → OU le reviewer (dans les 7 jours après création)
     */
    private function canUpdate(Review $review, User $user): bool
    {
        // 🆕 Les admins peuvent toujours modifier
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Règle 1 : C'est ton review
        if ($review->getReviewer() !== $user) {
            return false;
        }

        // Règle 2 : Moins de 7 jours depuis la création
        $createdAt = $review->getCreatedAt();
        $sevenDaysAgo = new \DateTimeImmutable('-7 days');

        return $createdAt >= $sevenDaysAgo;
    }

    /**
     * Peut supprimer la review ?
     * → Géré par l'ApiResource security (ROLE_ADMIN uniquement)
     * → Ce Voter retourne false, la sécurité API Resource prend le relais
     */
    private function canDelete(Review $review, User $user): bool
    {
        // Seuls les admins peuvent supprimer (géré dans ApiResource)
        return false;
    }
}

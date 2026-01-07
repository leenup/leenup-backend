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
    public const VIEW = 'REVIEW_VIEW';
    public const UPDATE = 'REVIEW_UPDATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Review) {
            return false;
        }

        return in_array($attribute, [
            self::VIEW,
            self::UPDATE,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Review $review */
        $review = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($review, $user),
            self::UPDATE => $this->canUpdate($review, $user),
            default => false,
        };
    }

    private function canView(Review $review, User $user): bool
    {
        return true;
    }

    private function canUpdate(Review $review, User $user): bool
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        if ($review->getReviewer()?->getId() !== $user->getId()) {
            return false;
        }

        $createdAt = $review->getCreatedAt();
        if (!$createdAt instanceof \DateTimeImmutable) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $interval = $createdAt->diff($now);
        $days = (int) $interval->format('%a');

        // Autorisé uniquement si moins de 7 jours complets
        return $days < 7;
    }
}

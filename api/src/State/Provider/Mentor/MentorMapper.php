<?php

namespace App\State\Provider\Mentor;

use App\ApiResource\Mentor\Mentor;
use App\ApiResource\Mentor\MentorReview;
use App\ApiResource\Mentor\MentorSkill;
use App\Entity\Review;
use App\Entity\User;
use App\Entity\UserSkill;

/**
 * Transforme les entités en DTO publics de découverte des mentors.
 */
final class MentorMapper
{
    public function toMentor(User $user): Mentor
    {
        $mentor = new Mentor();
        $mentor->id = $user->getId();
        $mentor->firstName = $user->getFirstName();
        $mentor->lastName = $user->getLastName();
        $mentor->avatarUrl = $user->getAvatarUrl();
        $mentor->bio = $user->getBio();
        $mentor->location = $user->getLocation();
        $mentor->languages = $user->getLanguages();
        $mentor->exchangeFormat = $user->getExchangeFormat();
        $mentor->averageRating = $user->getAverageRating() !== null ? (float) $user->getAverageRating() : null;
        $mentor->reviewCount = $user->getReviewCount();

        foreach ($user->getUserSkills() as $userSkill) {
            if ($userSkill->getType() !== UserSkill::TYPE_TEACH) {
                continue;
            }

            $skill = $userSkill->getSkill();
            $item = new MentorSkill();
            $item->skillId = $skill?->getId();
            $item->title = $skill?->getTitle();
            $item->categoryId = $skill?->getCategory()?->getId();
            $item->categoryTitle = $skill?->getCategory()?->getTitle();
            $item->level = $userSkill->getLevel();
            $mentor->skills[] = $item;
        }

        return $mentor;
    }

    public function toMentorReview(Review $review): MentorReview
    {
        $item = new MentorReview();
        $item->rating = $review->getRating();
        $item->comment = $review->getComment();
        $item->createdAt = $review->getCreatedAt();
        $item->reviewerFirstName = $review->getReviewer()?->getFirstName();
        $item->reviewerAvatarUrl = $review->getReviewer()?->getAvatarUrl();

        return $item;
    }
}

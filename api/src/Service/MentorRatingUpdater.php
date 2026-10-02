<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ReviewRepository;

/**
 * Recalcule la note moyenne et le nombre de reviews d'un mentor.
 * À appeler après chaque création, modification ou suppression de review (une fois le flush fait).
 */
final class MentorRatingUpdater
{
    public function __construct(
        private ReviewRepository $reviewRepository,
    ) {
    }

    public function update(User $mentor): void
    {
        ['average' => $average, 'count' => $count] = $this->reviewRepository->getRatingStatsForMentor($mentor);

        $mentor->setReviewCount($count);
        $mentor->setAverageRating($count > 0 ? number_format($average, 2, '.', '') : null);
    }
}

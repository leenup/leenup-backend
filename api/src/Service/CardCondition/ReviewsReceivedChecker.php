<?php

namespace App\Service\CardCondition;

use App\Entity\User;
use App\Repository\ReviewRepository;

class ReviewsReceivedChecker implements ConditionCheckerInterface
{
    public function __construct(
        private ReviewRepository $reviewRepository,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'reviews_received';
    }

    public function check(User $user, array $params): bool
    {
        // On compte les reviews où l'utilisateur est le mentor "reviewed"
        $count = $this->reviewRepository->count([
            'reviewed' => $user,
        ]);

        $operator = $params['operator'] ?? '>=';
        $value = $params['value'] ?? null;

        if ($value === null) {
            return false;
        }

        return $this->compare($count, $operator, $value);
    }

    private function compare(int $actual, string $operator, int $expected): bool
    {
        return match ($operator) {
            '>=' => $actual >= $expected,
            '>'  => $actual > $expected,
            '<=' => $actual <= $expected,
            '<'  => $actual < $expected,
            '==' => $actual === $expected,
            '!=' => $actual !== $expected,
            default => false,
        };
    }
}

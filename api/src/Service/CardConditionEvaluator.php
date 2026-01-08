<?php

namespace App\Service;

use App\Entity\Card;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Repository\SessionRepository;

final class CardConditionEvaluator
{
    public function __construct(
        private SessionRepository $sessionRepository,
        private ReviewRepository $reviewRepository,
    ) {
    }

    public function matches(User $user, Card $card): bool
    {
        $conditions = $card->getConditions();

        if ($conditions === []) {
            return false;
        }

        return $this->evaluateConditions($user, $conditions);
    }

    private function evaluateConditions(User $user, array $conditions): bool
    {
        if (array_key_exists('all', $conditions)) {
            $group = $conditions['all'];
            if (!is_array($group) || $group === []) {
                return false;
            }

            foreach ($group as $condition) {
                if (!is_array($condition) || !$this->evaluateConditions($user, $condition)) {
                    return false;
                }
            }

            return true;
        }

        if (array_key_exists('any', $conditions)) {
            $group = $conditions['any'];
            if (!is_array($group) || $group === []) {
                return false;
            }

            foreach ($group as $condition) {
                if (is_array($condition) && $this->evaluateConditions($user, $condition)) {
                    return true;
                }
            }

            return false;
        }

        if (!array_key_exists('type', $conditions)) {
            return false;
        }

        $type = $conditions['type'];
        if (!is_string($type) || $type === '') {
            return false;
        }

        $operator = $conditions['operator'] ?? '==';
        if (!is_string($operator)) {
            return false;
        }

        $expected = $conditions['value'] ?? null;

        $actual = match ($type) {
            'sessions_given' => $this->countCompletedMentorSessions($user),
            'sessions_taken' => $this->countCompletedStudentSessions($user),
            'reviews_received' => $this->countReviewsReceived($user),
            'skills_count' => $user->getUserSkills()->count(),
            'has_avatar' => $this->hasValue($user->getAvatarUrl()),
            'has_bio' => $this->hasValue($user->getBio()),
            default => null,
        };

        if ($actual === null) {
            return false;
        }

        return $this->compare($actual, $operator, $expected);
    }

    private function hasValue(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function compare(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=', '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }

    private function countCompletedMentorSessions(User $user): int
    {
        return (int) $this->sessionRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.mentor = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Session::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countCompletedStudentSessions(User $user): int
    {
        return (int) $this->sessionRepository->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.student = :user')
            ->andWhere('s.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Session::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countReviewsReceived(User $user): int
    {
        return (int) $this->reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.session', 's')
            ->andWhere('s.mentor = :mentor')
            ->setParameter('mentor', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

<?php

namespace App\Service\CardCondition;

use App\Entity\User;
use App\Repository\SessionRepository;

class SessionsGivenChecker implements ConditionCheckerInterface
{
    public function __construct(
        private SessionRepository $sessionRepository,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'sessions_given';
    }

    public function check(User $user, array $params): bool
    {
        // On compte les sessions complétées où l'utilisateur est mentor
        $count = $this->sessionRepository->count([
            'mentor' => $user,
            'status' => 'completed',
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

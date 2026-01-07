<?php

namespace App\Service\CardCondition;

use App\Entity\User;

class ProfileCompletedChecker implements ConditionCheckerInterface
{
    public function supports(string $type): bool
    {
        // Ce checker gère plusieurs "sub-types" logiques :
        // - has_avatar
        // - has_bio
        // - skills_count
        return in_array($type, ['has_avatar', 'has_bio', 'skills_count'], true);
    }

    public function check(User $user, array $params): bool
    {
        $type = $params['type'] ?? null;

        if ($type === null) {
            return false;
        }

        return match ($type) {
            'has_avatar' => $this->checkHasAvatar($user, $params),
            'has_bio' => $this->checkHasBio($user, $params),
            'skills_count' => $this->checkSkillsCount($user, $params),
            default => false,
        };
    }

    private function checkHasAvatar(User $user, array $params): bool
    {
        $expected = $params['value'] ?? true;

        $hasAvatar = $user->getAvatarUrl() !== null && $user->getAvatarUrl() !== '';

        return $expected ? $hasAvatar : !$hasAvatar;
    }

    private function checkHasBio(User $user, array $params): bool
    {
        $expected = $params['value'] ?? true;

        $hasBio = $user->getBio() !== null && trim($user->getBio()) !== '';

        return $expected ? $hasBio : !$hasBio;
    }

    private function checkSkillsCount(User $user, array $params): bool
    {
        $count = $user->getUserSkills()->count();

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

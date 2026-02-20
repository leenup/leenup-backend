<?php

namespace App\Service;

use App\Entity\MentorAvailabilityRule;
use App\Entity\User;
use App\Repository\MentorAvailabilityRuleRepository;
use App\Repository\SessionRepository;

class AvailabilityGuard
{
    public function __construct(
        private MentorAvailabilityRuleRepository $ruleRepository,
        private SessionRepository $sessionRepository,
    ) {
    }

    public function isDateAvailable(User $mentor, \DateTimeImmutable $start, int $duration): bool
    {
        $end = $start->modify(sprintf('+%d minutes', $duration));
        $rules = $this->ruleRepository->findActiveByMentor($mentor);

        $hasInclusion = false;
        foreach ($rules as $rule) {
            if (!$this->matchesRule($rule, $start, $end)) {
                continue;
            }

            if ($rule->getType() === MentorAvailabilityRule::TYPE_EXCLUSION) {
                return false;
            }

            $hasInclusion = true;
        }

        if (!$hasInclusion) {
            return false;
        }

        return !$this->sessionRepository->hasOverlappingActiveSession($mentor, $start, $end);
    }

    private function matchesRule(MentorAvailabilityRule $rule, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        if ($rule->getType() === MentorAvailabilityRule::TYPE_WEEKLY ||
            ($rule->getType() === MentorAvailabilityRule::TYPE_EXCLUSION && $rule->getDayOfWeek() !== null)) {
            if ($rule->getDayOfWeek() !== (int) $start->format('N')) {
                return false;
            }

            if (!$rule->getStartTime() || !$rule->getEndTime()) {
                return false;
            }

            $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
            $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');
            $ruleStart = ((int) $rule->getStartTime()->format('H') * 60) + (int) $rule->getStartTime()->format('i');
            $ruleEnd = ((int) $rule->getEndTime()->format('H') * 60) + (int) $rule->getEndTime()->format('i');

            return $startMinutes >= $ruleStart && $endMinutes <= $ruleEnd;
        }

        if ($rule->getStartsAt() && $rule->getEndsAt()) {
            return $start >= $rule->getStartsAt() && $end <= $rule->getEndsAt();
        }

        return false;
    }
}

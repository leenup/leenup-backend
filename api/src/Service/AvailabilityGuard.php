<?php

namespace App\Service;

use App\Entity\MentorAvailabilityRule;
use App\Entity\Session;
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

    /**
     * @param Session|null $ignoredSession session à ignorer pour le contrôle de chevauchement (replanification)
     */
    public function isDateAvailable(User $mentor, \DateTimeImmutable $start, int $duration, ?Session $ignoredSession = null): bool
    {
        $end = $start->modify(sprintf('+%d minutes', $duration));

        if (!$this->matchesRules($this->ruleRepository->findActiveByMentor($mentor), $start, $end)) {
            return false;
        }

        return !$this->sessionRepository->hasOverlappingActiveSession($mentor, $start, $end, $ignoredSession);
    }

    /**
     * Calcule les créneaux disponibles en chargeant règles et sessions une seule fois.
     *
     * @return \DateTimeImmutable[] débuts des créneaux disponibles
     */
    public function findAvailableStarts(User $mentor, \DateTimeImmutable $from, \DateTimeImmutable $to, int $duration, int $stepMinutes = 30): array
    {
        $rules = $this->ruleRepository->findActiveByMentor($mentor);
        $busyRanges = $this->sessionRepository->findActiveSessionRanges($mentor, $from, $to->modify(sprintf('+%d minutes', $duration)));

        $starts = [];
        for ($cursor = $from; $cursor < $to; $cursor = $cursor->modify(sprintf('+%d minutes', $stepMinutes))) {
            $end = $cursor->modify(sprintf('+%d minutes', $duration));

            if (!$this->matchesRules($rules, $cursor, $end)) {
                continue;
            }

            foreach ($busyRanges as [$busyStart, $busyEnd]) {
                if ($busyStart < $end && $busyEnd > $cursor) {
                    continue 2;
                }
            }

            $starts[] = $cursor;
        }

        return $starts;
    }

    /**
     * Un créneau est valide s'il est entièrement couvert par une règle d'inclusion
     * et ne chevauche aucune exclusion. Sans aucune règle, le mentor est disponible.
     *
     * @param MentorAvailabilityRule[] $rules
     */
    private function matchesRules(array $rules, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        if ($rules === []) {
            return true;
        }

        $hasInclusion = false;
        foreach ($rules as $rule) {
            if ($rule->getType() === MentorAvailabilityRule::TYPE_EXCLUSION) {
                if ($this->overlapsRule($rule, $start, $end)) {
                    return false;
                }

                continue;
            }

            if (!$hasInclusion && $this->containsSlot($rule, $start, $end)) {
                $hasInclusion = true;
            }
        }

        return $hasInclusion;
    }

    private function containsSlot(MentorAvailabilityRule $rule, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        foreach ($this->getRuleWindows($rule, $start) as [$windowStart, $windowEnd]) {
            if ($start >= $windowStart && $end <= $windowEnd) {
                return true;
            }
        }

        return false;
    }

    private function overlapsRule(MentorAvailabilityRule $rule, \DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        foreach ($this->getRuleWindows($rule, $start) as [$windowStart, $windowEnd]) {
            if ($windowStart < $end && $windowEnd > $start) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fenêtres concrètes d'une règle autour de $start.
     * Les règles hebdo sont évaluées dans le fuseau de la règle (heures "murales"),
     * et une plage dont la fin est <= au début passe minuit (ex : 22:00 → 02:00).
     *
     * @return array<array{0: \DateTimeImmutable, 1: \DateTimeImmutable}>
     */
    private function getRuleWindows(MentorAvailabilityRule $rule, \DateTimeImmutable $start): array
    {
        if ($rule->getDayOfWeek() === null) {
            if ($rule->getStartsAt() === null || $rule->getEndsAt() === null) {
                return [];
            }

            return [[$rule->getStartsAt(), $rule->getEndsAt()]];
        }

        if ($rule->getStartTime() === null || $rule->getEndTime() === null) {
            return [];
        }

        $timezone = new \DateTimeZone($rule->getTimezone());
        $localStart = $start->setTimezone($timezone);

        $ruleStartMinutes = $this->minutesOfDay($rule->getStartTime());
        $ruleEndMinutes = $this->minutesOfDay($rule->getEndTime());
        if ($ruleEndMinutes <= $ruleStartMinutes) {
            $ruleEndMinutes += 24 * 60;
        }

        $windows = [];
        // La veille est incluse pour couvrir la fin d'une plage qui passe minuit.
        foreach ([-1, 0] as $dayOffset) {
            $day = $localStart->setTime(0, 0)->modify(sprintf('%+d days', $dayOffset));
            if ((int) $day->format('N') !== $rule->getDayOfWeek()) {
                continue;
            }

            $windows[] = [
                $day->setTime(intdiv($ruleStartMinutes, 60), $ruleStartMinutes % 60),
                $day->modify(sprintf('+%d minutes', $ruleEndMinutes)),
            ];
        }

        return $windows;
    }

    private function minutesOfDay(\DateTimeInterface $time): int
    {
        return ((int) $time->format('H') * 60) + (int) $time->format('i');
    }
}

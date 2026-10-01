<?php

namespace App\State\Provider\Mentor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Mentor\MentorAvailableSlot;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AvailabilityGuard;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProviderInterface<MentorAvailableSlot[]>
 */
final class MentorAvailableSlotProvider implements ProviderInterface
{
    public const STEP_MINUTES = 30;
    public const DEFAULT_RANGE_DAYS = 14;
    public const MAX_RANGE_DAYS = 31;

    public function __construct(
        private UserRepository $userRepository,
        private AvailabilityGuard $availabilityGuard,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $mentorId = (int) ($uriVariables['id'] ?? 0);
        $mentor = $this->userRepository->find($mentorId);

        if (!$mentor instanceof User || !$mentor->isMentor()) {
            return [];
        }

        $request = $context['request'] ?? null;
        $duration = $this->parseDuration($request?->query->get('duration'));

        // Pas de créneaux dans le passé : on démarre au plus tôt au prochain pas de 30 min.
        $now = $this->roundUpToStep(new \DateTimeImmutable('now'));
        $fromDate = $this->parseDate($request?->query->get('from'), 'from') ?? $now;
        if ($fromDate < $now) {
            $fromDate = $now->setTimezone($fromDate->getTimezone());
        }

        $toDate = $this->parseDate($request?->query->get('to'), 'to') ?? $fromDate->modify(sprintf('+%d days', self::DEFAULT_RANGE_DAYS));

        if ($toDate <= $fromDate) {
            return [];
        }

        if ($toDate > $fromDate->modify(sprintf('+%d days', self::MAX_RANGE_DAYS))) {
            throw new BadRequestHttpException(sprintf('The range between "from" and "to" cannot exceed %d days.', self::MAX_RANGE_DAYS));
        }

        $slots = [];
        foreach ($this->availabilityGuard->findAvailableStarts($mentor, $fromDate, $toDate, $duration, self::STEP_MINUTES) as $start) {
            $item = new MentorAvailableSlot();
            $item->id = $mentorId.'-'.$start->format('c').'-'.$duration;
            $item->startAt = $start;
            $item->endAt = $start->modify(sprintf('+%d minutes', $duration));
            $item->duration = $duration;
            $slots[] = $item;
        }

        return $slots;
    }

    private function parseDuration(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 60;
        }

        $duration = filter_var($value, FILTER_VALIDATE_INT);
        if ($duration === false || $duration < 1 || $duration > Session::MAX_DURATION) {
            throw new BadRequestHttpException(sprintf('"duration" must be an integer between 1 and %d.', Session::MAX_DURATION));
        }

        return $duration;
    }

    private function parseDate(mixed $value, string $name): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            throw new BadRequestHttpException(sprintf('"%s" must be a valid date.', $name));
        }
    }

    private function roundUpToStep(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $step = self::STEP_MINUTES * 60;

        return $date->setTimestamp((int) ceil($date->getTimestamp() / $step) * $step);
    }
}

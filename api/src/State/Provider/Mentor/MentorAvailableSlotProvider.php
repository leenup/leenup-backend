<?php

namespace App\State\Provider\Mentor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Mentor\MentorAvailableSlot;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AvailabilityGuard;

/**
 * @implements ProviderInterface<MentorAvailableSlot[]>
 */
final class MentorAvailableSlotProvider implements ProviderInterface
{
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
        $from = $request?->query->get('from');
        $to = $request?->query->get('to');
        $duration = (int) ($request?->query->get('duration', 60) ?? 60);

        $fromDate = $from ? new \DateTimeImmutable($from) : new \DateTimeImmutable('now');
        $toDate = $to ? new \DateTimeImmutable($to) : $fromDate->modify('+14 days');

        $slots = [];
        $cursor = $fromDate;

        while ($cursor < $toDate) {
            if ($this->availabilityGuard->isDateAvailable($mentor, $cursor, $duration)) {
                $item = new MentorAvailableSlot();
                $item->id = $mentorId.'-'.$cursor->format('c').'-'.$duration;
                $item->startAt = $cursor;
                $item->endAt = $cursor->modify(sprintf('+%d minutes', $duration));
                $item->duration = $duration;
                $slots[] = $item;
            }

            $cursor = $cursor->modify('+30 minutes');
        }

        return $slots;
    }
}

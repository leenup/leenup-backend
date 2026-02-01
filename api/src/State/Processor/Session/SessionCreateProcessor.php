<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Entity\MentorAvailabilityException;
use App\Repository\MentorAvailabilityExceptionRepository;
use App\Repository\MentorAvailabilityRepository;
use App\Repository\UserSkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Session, Session>
 */
final class SessionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSkillRepository $userSkillRepository,
        private MentorAvailabilityRepository $availabilityRepository,
        private MentorAvailabilityExceptionRepository $availabilityExceptionRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        if (!$data instanceof Session) {
            throw new \LogicException('Expected Session entity');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // FORCER student = currentUser (ignorer le payload)
        $data->setStudent($currentUser);

        $mentor = $data->getMentor();

        if (!$mentor instanceof User) {
            throw new \LogicException('Mentor not provided');
        }

        if ($mentor === $data->getStudent()) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'You cannot be your own mentor',
                    null,
                    [],
                    $data,
                    'mentor',
                    $data->getMentor()
                )
            ]);
            throw new ValidationException($violations);
        }

        $isPerfectMatch = $this->userSkillRepository->hasPerfectMatch($currentUser, $mentor);

        if (!$isPerfectMatch && $currentUser->getTokenBalance() < 1) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'You need at least 1 token to join a session as a student',
                    null,
                    [],
                    $data,
                    'student',
                    $data->getStudent()
                )
            ]);
            throw new ValidationException($violations);
        }

        $mentorSkill = $this->userSkillRepository->findOneBy([
            'owner' => $mentor,
            'skill' => $data->getSkill(),
            'type' => UserSkill::TYPE_TEACH,
        ]);

        if (!$mentorSkill) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'The mentor must have this skill with type "teach"',
                    null,
                    [],
                    $data,
                    'mentor',
                    $data->getMentor()
                )
            ]);
            throw new ValidationException($violations);
        }

        $this->assertMentorAvailability($data, $mentor);

        if (!$isPerfectMatch) {
            $currentUser->removeTokenBalance(1);
        }
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }

    private function assertMentorAvailability(Session $session, User $mentor): void
    {
        $availabilities = $this->availabilityRepository->findBy(['mentor' => $mentor]);

        if (count($availabilities) === 0) {
            return;
        }

        $scheduledAt = $session->getScheduledAt();
        $duration = $session->getDuration();

        if (!$scheduledAt || !$duration) {
            return;
        }

        $sessionStart = $scheduledAt;
        $sessionEnd = $scheduledAt->modify(sprintf('+%d minutes', $duration));
        $sessionDate = $scheduledAt->setTime(0, 0, 0);

        $exceptions = $this->availabilityExceptionRepository->findBy([
            'mentor' => $mentor,
            'date' => $sessionDate,
        ]);

        $overrideExceptions = array_filter(
            $exceptions,
            fn($exception) => $exception->getType() === MentorAvailabilityException::TYPE_OVERRIDE
        );

        foreach ($exceptions as $exception) {
            if ($exception->getType() !== MentorAvailabilityException::TYPE_UNAVAILABLE) {
                continue;
            }

            $startTime = $exception->getStartTime();
            $endTime = $exception->getEndTime();

            if (!$startTime || !$endTime) {
                $this->throwAvailabilityViolation();
            }

            $blockStart = $this->combineDateAndTime($sessionDate, $startTime);
            $blockEnd = $this->combineDateAndTime($sessionDate, $endTime);

            if ($this->overlaps($sessionStart, $sessionEnd, $blockStart, $blockEnd)) {
                $this->throwAvailabilityViolation();
            }
        }

        $availabilityWindows = [];

        if (count($overrideExceptions) > 0) {
            foreach ($overrideExceptions as $exception) {
                $startTime = $exception->getStartTime();
                $endTime = $exception->getEndTime();

                if (!$startTime || !$endTime) {
                    $availabilityWindows[] = [$sessionDate->setTime(0, 0), $sessionDate->setTime(23, 59)];
                    continue;
                }

                $availabilityWindows[] = [
                    $this->combineDateAndTime($sessionDate, $startTime),
                    $this->combineDateAndTime($sessionDate, $endTime),
                ];
            }
        } else {
            $dayOfWeek = (int) $scheduledAt->format('w');

            foreach ($availabilities as $availability) {
                if ($availability->getDayOfWeek() !== $dayOfWeek) {
                    continue;
                }

                $availabilityWindows[] = [
                    $this->combineDateAndTime($sessionDate, $availability->getStartTime()),
                    $this->combineDateAndTime($sessionDate, $availability->getEndTime()),
                ];
            }
        }

        if (count($availabilityWindows) === 0) {
            $this->throwAvailabilityViolation();
        }

        foreach ($availabilityWindows as [$windowStart, $windowEnd]) {
            if ($sessionStart >= $windowStart && $sessionEnd <= $windowEnd) {
                return;
            }
        }

        $this->throwAvailabilityViolation();
    }

    private function combineDateAndTime(\DateTimeImmutable $date, \DateTimeImmutable $time): \DateTimeImmutable
    {
        return $date->setTime(
            (int) $time->format('H'),
            (int) $time->format('i'),
            (int) $time->format('s')
        );
    }

    private function overlaps(\DateTimeImmutable $startA, \DateTimeImmutable $endA, \DateTimeImmutable $startB, \DateTimeImmutable $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    private function throwAvailabilityViolation(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'The requested time is outside of the mentor availability',
                null,
                [],
                null,
                'scheduledAt',
                null
            )
        ]);

        throw new ValidationException($violations);
    }
}

<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Session;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Session, Session>
 */
final class SessionUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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

        $isMentor = $data->getMentor() === $currentUser;
        $isStudent = $data->getStudent() === $currentUser;

        if (!$isMentor && !$isStudent) {
            throw new AccessDeniedHttpException('You can only update your own sessions');
        }

        $originalData = $context['previous_data'] ?? null;

        if ($originalData instanceof Session) {
            if ($data->getMentor() !== $originalData->getMentor() ||
                $data->getStudent() !== $originalData->getStudent() ||
                $data->getSkill() !== $originalData->getSkill()) {
                $violations = new ConstraintViolationList([
                    new \Symfony\Component\Validator\ConstraintViolation(
                        'You cannot change the mentor, student, or skill of a session',
                        null,
                        [],
                        $data,
                        'mentor',
                        null
                    )
                ]);
                throw new ValidationException($violations);
            }

            $oldStatus = $originalData->getStatus();
            $newStatus = $data->getStatus();

            if ($oldStatus !== $newStatus) {
                if (in_array($oldStatus, [Session::STATUS_CANCELLED, Session::STATUS_COMPLETED])) {
                    $violations = new ConstraintViolationList([
                        new \Symfony\Component\Validator\ConstraintViolation(
                            'Cannot change status from ' . $oldStatus,
                            null,
                            [],
                            $data,
                            'status',
                            $newStatus
                        )
                    ]);
                    throw new ValidationException($violations);
                }

                if ($oldStatus === Session::STATUS_PENDING && $newStatus === Session::STATUS_COMPLETED) {
                    $violations = new ConstraintViolationList([
                        new \Symfony\Component\Validator\ConstraintViolation(
                            'A pending session must be confirmed before being completed',
                            null,
                            [],
                            $data,
                            'status',
                            $newStatus
                        )
                    ]);
                    throw new ValidationException($violations);
                }

                if ($newStatus === Session::STATUS_CONFIRMED && !$isMentor) {
                    $violations = new ConstraintViolationList([
                        new \Symfony\Component\Validator\ConstraintViolation(
                            'Only the mentor can confirm a session',
                            null,
                            [],
                            $data,
                            'status',
                            $newStatus
                        )
                    ]);
                    throw new ValidationException($violations);
                }

                if ($newStatus === Session::STATUS_COMPLETED && !$isMentor) {
                    $violations = new ConstraintViolationList([
                        new \Symfony\Component\Validator\ConstraintViolation(
                            'Only the mentor can mark a session as completed',
                            null,
                            [],
                            $data,
                            'status',
                            $newStatus
                        )
                    ]);
                    throw new ValidationException($violations);
                }
            }
        }

        $this->entityManager->flush();

        return $data;
    }
}

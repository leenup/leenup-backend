<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Session;
use App\Security\Voter\SessionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Session, Session>
 */
final class SessionStatusProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuthorizationCheckerInterface $authChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        if (!$data instanceof Session) {
            throw new \LogicException('Expected Session entity');
        }

        $targetStatus = $this->getTargetStatus($operation);

        $this->validateStatusTransition($data, $targetStatus);
        $this->assertPermission($data, $targetStatus);

        if ($data->getStatus() !== $targetStatus) {
            $data->setStatus($targetStatus);
            $this->entityManager->flush();
        }

        return $data;
    }

    private function getTargetStatus(Operation $operation): string
    {
        $extraProperties = $operation->getExtraProperties();
        $targetStatus = $extraProperties['target_status'] ?? null;

        if (!is_string($targetStatus) || $targetStatus === '') {
            throw new \LogicException('Missing target_status for session status operation');
        }

        return $targetStatus;
    }

    private function assertPermission(Session $session, string $targetStatus): void
    {
        $attribute = match ($targetStatus) {
            Session::STATUS_CONFIRMED => SessionVoter::CONFIRM,
            Session::STATUS_COMPLETED => SessionVoter::COMPLETE,
            Session::STATUS_CANCELLED => SessionVoter::CANCEL,
            default => throw new \LogicException('Unsupported target_status value'),
        };

        $message = match ($targetStatus) {
            Session::STATUS_CONFIRMED => 'Only the mentor can confirm a session',
            Session::STATUS_COMPLETED => 'Only the mentor can mark a session as completed',
            Session::STATUS_CANCELLED => 'You cannot cancel this session',
            default => 'Access denied',
        };

        if (!$this->authChecker->isGranted($attribute, $session)) {
            throw new AccessDeniedHttpException($message);
        }
    }

    private function validateStatusTransition(Session $session, string $targetStatus): void
    {
        $oldStatus = $session->getStatus();

        if ($oldStatus === $targetStatus) {
            return;
        }

        if (in_array($oldStatus, [Session::STATUS_CANCELLED, Session::STATUS_COMPLETED], true)) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'Cannot change status from '.$oldStatus,
                    null,
                    [],
                    $session,
                    'status',
                    $targetStatus
                ),
            ]));
        }

        if ($oldStatus === Session::STATUS_PENDING && $targetStatus === Session::STATUS_COMPLETED) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'A pending session must be confirmed before being completed',
                    null,
                    [],
                    $session,
                    'status',
                    $targetStatus
                ),
            ]));
        }
    }
}

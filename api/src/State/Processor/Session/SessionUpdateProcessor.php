<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Session;
use App\Entity\User;
use App\Security\Voter\SessionVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Session, Session>
 */
final class SessionUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AuthorizationCheckerInterface $authChecker,
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

        // Vérification globale : l'utilisateur peut-il modifier cette session ?
        if (!$this->authChecker->isGranted(SessionVoter::UPDATE, $data)) {
            throw new AccessDeniedHttpException('You can only update your own sessions');
        }

        $originalData = $context['previous_data'] ?? null;

        if ($originalData instanceof Session) {
            // Vérifier que les champs immuables n'ont pas changé
            $this->validateImmutableFields($data, $originalData);

            // Vérifier les changements de statut
            $this->validateStatusTransition($data, $originalData);

            // Vérifier les changements d'horaire
            $this->validateScheduleUpdate($data, $originalData);
        }

        $this->entityManager->flush();

        return $data;
    }

    /**
     * Empêche la modification des champs immuables (mentor, student, skill)
     */
    private function validateImmutableFields(Session $new, Session $old): void
    {
        if ($new->getMentor() !== $old->getMentor()
            || $new->getStudent() !== $old->getStudent()
            || $new->getSkill() !== $old->getSkill()) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'You cannot change the mentor, student, or skill of a session',
                    null,
                    [],
                    $new,
                    'mentor',
                    null
                )
            ]));
        }
    }

    /**
     * Valide les transitions de statut en utilisant le Voter
     */
    private function validateStatusTransition(Session $new, Session $old): void
    {
        $oldStatus = $old->getStatus();
        $newStatus = $new->getStatus();

        // Si pas de changement de statut, pas de validation nécessaire
        if ($oldStatus === $newStatus) {
            return;
        }

        // Règle 1 : On ne peut pas modifier une session cancelled ou completed
        if (in_array($oldStatus, [Session::STATUS_CANCELLED, Session::STATUS_COMPLETED])) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'Cannot change status from ' . $oldStatus,
                    null,
                    [],
                    $new,
                    'status',
                    $newStatus
                )
            ]));
        }

        // Règle 2 : Transition pending → completed interdite (il faut passer par confirmed)
        if ($oldStatus === Session::STATUS_PENDING && $newStatus === Session::STATUS_COMPLETED) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'A pending session must be confirmed before being completed',
                    null,
                    [],
                    $new,
                    'status',
                    $newStatus
                )
            ]));
        }

        // Règle 3 : Pour confirmer, il faut avoir la permission SESSION_CONFIRM
        if ($newStatus === Session::STATUS_CONFIRMED) {
            if (!$this->authChecker->isGranted(SessionVoter::CONFIRM, $old)) {
                throw new AccessDeniedHttpException('Only the mentor can confirm a session');
            }
        }

        // Règle 4 : Pour compléter, il faut avoir la permission SESSION_COMPLETE
        if ($newStatus === Session::STATUS_COMPLETED) {
            if (!$this->authChecker->isGranted(SessionVoter::COMPLETE, $old)) {
                throw new AccessDeniedHttpException('Only the mentor can mark a session as completed');
            }
        }

        // Règle 5 : Pour annuler, il faut avoir la permission SESSION_CANCEL
        if ($newStatus === Session::STATUS_CANCELLED) {
            if (!$this->authChecker->isGranted(SessionVoter::CANCEL, $old)) {
                throw new AccessDeniedHttpException('You cannot cancel this session');
            }
        }
    }

    /**
     * Vérifie si l'utilisateur peut modifier l'horaire (scheduledAt, duration)
     */
    private function validateScheduleUpdate(Session $new, Session $old): void
    {
        $scheduleChanged = $new->getScheduledAt() != $old->getScheduledAt()
            || $new->getDuration() !== $old->getDuration();

        if ($scheduleChanged) {
            if (!$this->authChecker->isGranted(SessionVoter::UPDATE_SCHEDULE, $new)) {
                throw new AccessDeniedHttpException('Only the mentor can modify the schedule');
            }
        }
    }
}

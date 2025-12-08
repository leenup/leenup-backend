<?php

namespace App\Security\Voter;

use App\Entity\Session;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter pour gérer les autorisations sur les sessions
 */
class SessionVoter extends Voter
{
    // Constantes pour les permissions
    public const VIEW = 'SESSION_VIEW';
    public const UPDATE = 'SESSION_UPDATE';
    public const DELETE = 'SESSION_DELETE';
    public const CANCEL = 'SESSION_CANCEL';
    public const CONFIRM = 'SESSION_CONFIRM';
    public const COMPLETE = 'SESSION_COMPLETE';
    public const UPDATE_SCHEDULE = 'SESSION_UPDATE_SCHEDULE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Ce voter ne s'applique que sur les objets Session
        if (!$subject instanceof Session) {
            return false;
        }

        // Et uniquement pour les permissions qu'on gère
        return in_array($attribute, [
            self::VIEW,
            self::UPDATE,
            self::DELETE,
            self::CANCEL,
            self::CONFIRM,
            self::COMPLETE,
            self::UPDATE_SCHEDULE,
        ]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        /** @var Session $session */
        $session = $subject;

        // Déléguer la vérification selon la permission demandée
        return match ($attribute) {
            self::VIEW => $this->canView($session, $user),
            self::UPDATE => $this->canUpdate($session, $user),
            self::DELETE => $this->canDelete($session, $user),
            self::CANCEL => $this->canCancel($session, $user),
            self::CONFIRM => $this->canConfirm($session, $user),
            self::COMPLETE => $this->canComplete($session, $user),
            self::UPDATE_SCHEDULE => $this->canUpdateSchedule($session, $user),
            default => false,
        };
    }

    /**
     * Peut voir la session ?
     * → Mentor, Student ou Admin
     */
    private function canView(Session $session, User $user): bool
    {
        return $session->getMentor() === $user
            || $session->getStudent() === $user;
    }

    /**
     * Peut modifier des champs généraux (notes, etc.) ?
     * → Mentor ou Student
     */
    private function canUpdate(Session $session, User $user): bool
    {
        return $session->getMentor() === $user
            || $session->getStudent() === $user;
    }

    /**
     * Peut supprimer la session ?
     * → Mentor ou Student (si status = pending)
     */
    private function canDelete(Session $session, User $user): bool
    {
        $isParticipant = $session->getMentor() === $user
            || $session->getStudent() === $user;

        // On ne peut supprimer que si pending
        return $isParticipant && $session->getStatus() === Session::STATUS_PENDING;
    }

    /**
     * Peut annuler la session ?
     * → Mentor ou Student (si pas déjà completed)
     */
    private function canCancel(Session $session, User $user): bool
    {
        $isParticipant = $session->getMentor() === $user
            || $session->getStudent() === $user;

        // On ne peut pas annuler une session déjà completed
        return $isParticipant && $session->getStatus() !== Session::STATUS_COMPLETED;
    }

    /**
     * Peut confirmer la session ?
     * → UNIQUEMENT le mentor (si status = pending)
     */
    private function canConfirm(Session $session, User $user): bool
    {
        return $session->getMentor() === $user
            && $session->getStatus() === Session::STATUS_PENDING;
    }

    /**
     * Peut marquer la session comme complétée ?
     * → UNIQUEMENT le mentor (si status = confirmed)
     */
    private function canComplete(Session $session, User $user): bool
    {
        return $session->getMentor() === $user
            && $session->getStatus() === Session::STATUS_CONFIRMED;
    }

    /**
     * Peut modifier l'horaire (scheduledAt, duration) ?
     * → UNIQUEMENT le mentor
     */
    private function canUpdateSchedule(Session $session, User $user): bool
    {
        return $session->getMentor() === $user;
    }
}

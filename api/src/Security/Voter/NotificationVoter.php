<?php
// api/src/Security/Voter/NotificationVoter.php

namespace App\Security\Voter;

use App\Entity\Notification;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class NotificationVoter extends Voter
{
    public const VIEW = 'NOTIFICATION_VIEW';
    public const UPDATE = 'NOTIFICATION_UPDATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Notification) {
            return false;
        }

        return in_array($attribute, [self::VIEW, self::UPDATE]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Notification $notification */
        $notification = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($notification, $user),
            self::UPDATE => $this->canUpdate($notification, $user),
            default => false,
        };
    }

    private function canView(Notification $notification, User $user): bool
    {
        // Un user ne peut voir que ses propres notifications
        return $notification->getUser() === $user;
    }

    private function canUpdate(Notification $notification, User $user): bool
    {
        // Un user ne peut modifier (marquer comme lu) que ses propres notifications
        return $notification->getUser() === $user;
    }
}

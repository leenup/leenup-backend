<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserSkill;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserSkillVoter extends Voter
{
    public const VIEW   = 'USER_SKILL_VIEW';
    public const DELETE = 'USER_SKILL_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof UserSkill) {
            return false;
        }

        return in_array($attribute, [
            self::VIEW,
            self::DELETE,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var UserSkill $userSkill */
        $userSkill = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($userSkill, $user),
            self::DELETE => $this->canDelete($userSkill, $user),
            default => false,
        };
    }

    private function isOwner(UserSkill $userSkill, User $user): bool
    {
        return $userSkill->getOwner() === $user;
    }

    private function isAdmin(User $user): bool
    {
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Voir une UserSkill ?
     * Ici : tous les users authentifiés peuvent voir toutes les UserSkill.
     * Si tu veux restreindre : owner ou admin uniquement.
     */
    private function canView(UserSkill $userSkill, User $user): bool
    {
        return true;
        // return $this->isOwner($userSkill, $user) || $this->isAdmin($user);
    }

    /**
     * Supprimer une UserSkill ?
     * → Owner ou admin.
     */
    private function canDelete(UserSkill $userSkill, User $user): bool
    {
        return $this->isOwner($userSkill, $user) || $this->isAdmin($user);
    }
}

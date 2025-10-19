<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\CurrentUser\CurrentUser;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provider pour récupérer l'utilisateur actuellement connecté
 *
 * @implements ProviderInterface<CurrentUser>
 */
final class CurrentUserProvider implements ProviderInterface
{
    public function __construct(
        private Security $security
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CurrentUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        // Mapper User entity vers CurrentUser DTO
        $currentUser = new CurrentUser();
        $currentUser->id = $user->getId();
        $currentUser->email = $user->getEmail();
        $currentUser->roles = $user->getRoles();
        $currentUser->firstName = $user->getFirstName();
        $currentUser->lastName = $user->getLastName();
        $currentUser->avatarUrl = $user->getAvatarUrl();
        $currentUser->bio = $user->getBio();
        $currentUser->location = $user->getLocation();
        $currentUser->timezone = $user->getTimezone();
        $currentUser->locale = $user->getLocale();
        $currentUser->lastLoginAt = $user->getLastLoginAt();
        $currentUser->createdAt = $user->getCreatedAt();
        $currentUser->updatedAt = $user->getUpdatedAt();
        $currentUser->userSkills = $user->getUserSkills()->toArray();

        return $currentUser;
    }
}

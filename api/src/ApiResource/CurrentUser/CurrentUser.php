<?php

namespace App\ApiResource\CurrentUser;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\User;
use App\State\Processor\Profile\CurrentUserProcessor;
use App\State\Processor\Profile\CurrentUserRemoveProcessor;
use App\State\Provider\Profile\CurrentUserProvider;

/**
 * Ressource API pour le profil de l'utilisateur connecté (/me)
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to access your profile.',
            provider: CurrentUserProvider::class,
        ),
        new Patch(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to update your profile.',
            validate: false,
            provider: CurrentUserProvider::class,
            processor: CurrentUserProcessor::class,
        ),
        new Delete(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to delete your account.',
            provider: CurrentUserProvider::class,
            processor: CurrentUserRemoveProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:update']],
)]
class CurrentUser extends User
{
}

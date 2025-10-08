<?php

namespace App\ApiResource\CurrentUser;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\User;
use App\State\Provider\Profile\CurrentUserProvider;

/**
 * Ressource API pour le profil de l'utilisateur connecté (/me)
 *
 * Cette classe hérite de User mais expose des endpoints différents
 * dédiés à la gestion du profil personnel de l'utilisateur connecté.
 */
#[ApiResource(
    shortName: 'User',
    operations: [
        new Get(
            uriTemplate: '/me',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: CurrentUserProvider::class,
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:update']],
)]
class CurrentUser extends User
{
}

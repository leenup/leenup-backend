<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Dto\ChangePasswordDto;
use App\State\Processor\Profile\ChangePasswordProcessor;
use App\State\Provider\Profile\ChangePasswordProvider;

/**
 * Ressource API pour le changement de mot de passe
 */
#[ApiResource(
    shortName: 'ChangePassword',
    operations: [
        new Post(
            uriTemplate: '/me/change-password',
            status: 204,
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            output: false,
            provider: ChangePasswordProvider::class,
            processor: ChangePasswordProcessor::class
        ),
    ],
)]
class ChangePassword extends ChangePasswordDto
{
}

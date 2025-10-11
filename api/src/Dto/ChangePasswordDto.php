<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour le changement de mot de passe
 */
class ChangePasswordDto
{
    #[Assert\NotBlank(message: 'The current password cannot be empty')]
    public ?string $currentPassword = null;

    #[Assert\NotBlank(message: 'The new password cannot be empty')]
    #[Assert\Length(
        min: 8,
        minMessage: 'The new password must be at least {{ limit }} characters long',
    )]
    public ?string $newPassword = null;
}

<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ChangePasswordDto;

/**
 * Provider pour le changement de mot de passe
 *
 * @implements ProviderInterface<ChangePasswordDto>
 */
final class ChangePasswordProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return new ChangePasswordDto();
    }
}

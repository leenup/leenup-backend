<?php

namespace App\ApiResource\CurrentUser;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\State\Processor\Profile\CurrentUserProcessor;
use App\State\Processor\Profile\CurrentUserRemoveProcessor;
use App\State\Provider\Profile\CurrentUserProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
            provider: CurrentUserProvider::class,
            processor: CurrentUserProcessor::class,
            validationContext: ['groups' => ['Default']],
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
class CurrentUser
{
    #[Groups(['user:read'])]
    public ?int $id = null;

    #[Assert\Email]
    #[Groups(['user:read', 'user:update'])]
    public ?string $email = null;

    #[Groups(['user:read'])]
    public array $roles = [];

    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['user:read', 'user:update'])]
    public ?string $firstName = null;

    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['user:read', 'user:update'])]
    public ?string $lastName = null;

    #[Assert\Url]
    #[Groups(['user:read', 'user:update'])]
    public ?string $avatarUrl = null;

    #[Assert\Length(max: 500)]
    #[Groups(['user:read', 'user:update'])]
    public ?string $bio = null;

    #[Groups(['user:read', 'user:update'])]
    public ?string $location = null;

    #[Groups(['user:read', 'user:update'])]
    public ?string $timezone = null;

    #[Groups(['user:read', 'user:update'])]
    public ?string $locale = null;

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $lastLoginAt = null;

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $updatedAt = null;

    public ?string $plainPassword = null;
}

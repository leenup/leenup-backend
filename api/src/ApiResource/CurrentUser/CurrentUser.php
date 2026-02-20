<?php

namespace App\ApiResource\CurrentUser;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\Processor\Profile\CurrentUserProcessor;
use App\State\Processor\Profile\CurrentUserRemoveProcessor;
use App\State\Provider\Profile\CurrentUserProvider;
use App\Controller\UploadProfileAvatarAction;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\Response;

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
            validationContext: ['groups' => ['Default']],
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
        new Post(
            uriTemplate: '/me/avatar',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to upload an avatar.',
            controller: UploadProfileAvatarAction::class,
            deserialize: false,
            validate: false,
            status: Response::HTTP_OK,
            inputFormats: ['multipart' => ['multipart/form-data']],
            outputFormats: ['jsonld' => ['application/ld+json']],
        ),
    ],
    normalizationContext: ['groups' => ['user:read', 'my_skill:read']],
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

    #[Groups(['user:read'])]
    public array $profiles = [];

    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['user:read', 'user:update'])]
    public ?string $firstName = null;

    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['user:read', 'user:update'])]
    public ?string $lastName = null;

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

    #[Groups(['user:read', 'user:update'])]
    public ?\DateTimeInterface $birthdate = null;

    /**
     * @var string[]|null
     */
    #[Groups(['user:read', 'user:update'])]
    public ?array $languages = null;

    #[Groups(['user:read', 'user:update'])]
    public ?string $exchangeFormat = null;

    /**
     * @var string[]|null
     */
    #[Groups(['user:read', 'user:update'])]
    public ?array $learningStyles = null;

    #[Groups(['user:read', 'user:update'])]
    public ?bool $isMentor = null;

    #[Groups(['user:read'])]
    public ?int $tokenBalance = null;

    #[Groups(['my_skill:read'])]
    public array $userSkills = [];

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $lastLoginAt = null;

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['user:read'])]
    public ?\DateTimeImmutable $updatedAt = null;

    public ?string $plainPassword = null;
}

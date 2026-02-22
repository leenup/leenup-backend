<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\Profile\UploadProfileAvatarAction;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/me/avatar',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to upload your profile image.',
            read: false,
            deserialize: false,
            controller: UploadProfileAvatarAction::class,
            output: ProfileAvatarUpload::class,
            inputFormats: ['multipart' => ['multipart/form-data']],
        ),
    ],
    normalizationContext: ['groups' => ['profile_avatar:read']],
)]
final class ProfileAvatarUpload
{
    #[Groups(['profile_avatar:read'])]
    public ?string $avatarUrl = null;

    #[Groups(['profile_avatar:read'])]
    public string $message = 'Profile image uploaded successfully.';
}

<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Controller\ProfileAvatarUploadController;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    shortName: 'ProfileAvatarUpload',
    operations: [
        new Post(
            uriTemplate: '/me/avatar',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to upload a profile image.',
            controller: ProfileAvatarUploadController::class,
            deserialize: false,
            read: false,
            validate: false,
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new OpenApiOperation(
                summary: 'Upload current user profile avatar',
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                                'required' => ['file'],
                            ],
                        ],
                    ])
                ),
            ),
        ),
    ],
    normalizationContext: ['groups' => ['profile_avatar_upload:read']],
)]
class ProfileAvatarUpload
{
    #[Groups(['profile_avatar_upload:read'])]
    public ?string $avatarUrl = null;
}


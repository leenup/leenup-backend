<?php

namespace App\ApiResource\Upload;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\State\Processor\Upload\CurrentUserAvatarUploadProcessor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'AvatarUpload',
    operations: [
        new Post(
            uriTemplate: '/me/avatar',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            securityMessage: 'You must be authenticated to upload an avatar.',
            inputFormats: ['multipart' => ['multipart/form-data']],
            outputFormats: ['jsonld' => ['application/ld+json']],
            processor: CurrentUserAvatarUploadProcessor::class,
            deserialize: true,
            openapi: new Operation(
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'file' => ['type' => 'string', 'format' => 'binary'],
                                ],
                                'required' => ['file'],
                            ],
                        ],
                    ])
                )
            )
        ),
    ],
    normalizationContext: ['groups' => ['avatar_upload:read']],
    denormalizationContext: ['groups' => ['avatar_upload:write']],
)]
class CurrentUserAvatarUpload
{
    #[Assert\NotNull]
    #[Assert\Image(
        maxSize: '5M',
        mimeTypesMessage: 'Please upload a valid image (jpeg, png, gif, webp, avif).',
    )]
    #[Groups(['avatar_upload:write'])]
    public ?UploadedFile $file = null;

    #[Groups(['avatar_upload:read'])]
    public ?string $avatarUrl = null;
}

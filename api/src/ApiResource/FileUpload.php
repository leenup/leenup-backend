<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Controller\UploadFileController;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/uploads/{type}',
            requirements: ['type' => 'profile|document|other'],
            controller: UploadFileController::class,
            read: false,
            deserialize: false,
            write: false,
            inputFormats: ['multipart' => ['multipart/form-data']],
            openapi: new OpenApiOperation(
                summary: 'Upload a file',
                description: 'Upload a file in the selected storage directory (profile, document, other).',
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
                    ]),
                ),
            ),
        ),
    ],
)]
final class FileUpload
{
}

<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\LogoutController;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/auth/logout',
            status: 204,
            controller: LogoutController::class,
            openapi: new OpenApiOperation(
                responses: [
                    '204' => new OpenApiResponse(description: 'you are logged out'),
                ],
                summary: 'logout the current user',
                description: 'access token and csrf cookies are removed',
            ),
            read: false,
            deserialize: false,
            write: false,
            name: 'auth_logout',
        ),
    ],
)]
final class AuthLogout
{
}

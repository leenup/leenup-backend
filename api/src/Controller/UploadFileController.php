<?php

namespace App\Controller;

use App\Service\FileUploader;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class UploadFileController
{
    public function __construct(
        private FileUploader $fileUploader,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $type): JsonResponse
    {
        if (!$this->security->isGranted('ROLE_USER')) {
            return new JsonResponse(['detail' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['detail' => 'Missing "file" field.'], Response::HTTP_BAD_REQUEST);
        }

        $uploaded = $this->fileUploader->upload($file, $type);
        $publicUrl = sprintf('%s://%s%s', $request->getScheme(), $request->getHttpHost(), $uploaded['path']);

        return new JsonResponse([
            'type' => $uploaded['type'],
            'filename' => $uploaded['filename'],
            'path' => $uploaded['path'],
            'url' => $publicUrl,
        ], Response::HTTP_CREATED);
    }
}

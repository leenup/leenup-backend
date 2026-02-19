<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final readonly class UploadUserProfileImageController
{
    public function __construct(
        private FileUploader $fileUploader,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(User $user, Request $request): User|JsonResponse
    {
        $currentUser = $this->security->getUser();
        if (!$this->security->isGranted('ROLE_ADMIN') && $currentUser !== $user) {
            return new JsonResponse(['detail' => 'You cannot edit this profile image.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['detail' => 'Missing "file" field.'], Response::HTTP_BAD_REQUEST);
        }

        $uploaded = $this->fileUploader->upload($file, 'profile');
        $publicUrl = sprintf('%s://%s%s', $request->getScheme(), $request->getHttpHost(), $uploaded['path']);

        $user->setAvatarUrl($publicUrl);
        $this->entityManager->flush();

        return $user;
    }
}

<?php

namespace App\Controller;

use App\Entity\MediaObject;
use App\Entity\User;
use App\Enum\UploadDirectoryEnum;
use App\Repository\MediaObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

final class CurrentUserAvatarUploadController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaObjectRepository $mediaObjectRepository,
        private readonly ValidatorInterface $validator,
        private readonly StorageInterface $storage,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $mediaObject = new MediaObject();
        $mediaObject->setDirectory(UploadDirectoryEnum::PROFILE);
        $mediaObject->setFile($request->files->get('file'));

        $violations = $this->validator->validate($mediaObject, null, ['Default', 'media_object:create']);
        if (count($violations) > 0) {
            return $this->json([
                '@type' => 'ConstraintViolationList',
                'violations' => array_map(static fn ($v) => [
                    'propertyPath' => $v->getPropertyPath(),
                    'message' => $v->getMessage(),
                ], iterator_to_array($violations)),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($mediaObject);
        $this->entityManager->flush();

        $newAvatarPath = $this->storage->resolveUri($mediaObject, 'file');
        $previousAvatarPath = $user->getAvatarUrl();

        $user->setAvatarUrl($newAvatarPath);
        $user->onPreUpdate();

        $this->cleanupPreviousAvatar($previousAvatarPath, $newAvatarPath);

        $this->entityManager->flush();

        return $this->json([
            '@type' => 'User',
            'avatarUrl' => $newAvatarPath,
        ], Response::HTTP_CREATED);
    }

    private function cleanupPreviousAvatar(?string $previousAvatarPath, string $newAvatarPath): void
    {
        if ($previousAvatarPath === null || $previousAvatarPath === $newAvatarPath) {
            return;
        }

        if (!str_starts_with($previousAvatarPath, '/upload/profile/')) {
            return;
        }

        $relativePath = ltrim(substr($previousAvatarPath, strlen('/upload/')), '/');

        $filesystem = new Filesystem();
        $fullPath = sprintf('%s/public/upload/%s', $this->projectDir, $relativePath);

        if ($filesystem->exists($fullPath)) {
            $filesystem->remove($fullPath);
        }

        $previousMediaObject = $this->mediaObjectRepository->findOneBy(['filePath' => $relativePath]);
        if ($previousMediaObject !== null) {
            $this->entityManager->remove($previousMediaObject);
        }
    }
}

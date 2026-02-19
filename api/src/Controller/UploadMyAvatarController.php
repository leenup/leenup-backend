<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/me/avatar', name: 'me_avatar_upload', methods: ['POST'])]
final class UploadMyAvatarController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['message' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $uploadedFile = $request->files->get('avatar');
        if ($uploadedFile === null) {
            return new JsonResponse(['message' => 'Missing "avatar" file'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($uploadedFile, [
            new File(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                maxSizeMessage: 'Avatar file is too large (max {{ limit }}).',
                mimeTypesMessage: 'Only JPEG, PNG and WEBP images are allowed.',
            )
        ]);

        if (count($violations) > 0) {
            return new JsonResponse(['message' => (string) $violations->get(0)->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $targetDir = $this->projectDir.'/public/uploads/leenup/avatars';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $mimeType = $uploadedFile->getMimeType() ?? 'image/jpeg';
        $extension = $extensionByMime[$mimeType] ?? 'jpg';
        $filename = sprintf('user-%s.%s', Uuid::v4(), $extension);

        $uploadedFile->move($targetDir, $filename);

        $avatarUrl = sprintf('%s/uploads/leenup/avatars/%s', $request->getSchemeAndHttpHost(), $filename);
        $user->setAvatarUrl($avatarUrl);
        $user->onPreUpdate();
        $this->entityManager->flush();

        return new JsonResponse([
            'avatarUrl' => $avatarUrl,
        ], Response::HTTP_OK);
    }
}

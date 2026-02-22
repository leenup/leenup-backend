<?php

namespace App\Controller;

use App\Entity\MediaObject;
use App\Enum\UploadDirectoryEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

final class MediaObjectUploadController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly StorageInterface $storage,
    ) {
    }

    #[Route('/media_objects', name: 'media_objects_upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $mediaObject = new MediaObject();

        $uploadedFile = $request->files->get('file');
        if ($uploadedFile !== null) {
            $mediaObject->setFile($uploadedFile);
        }

        $directory = (string) $request->request->get('directory', UploadDirectoryEnum::PROFILE->value);
        $mediaObject->setDirectory(UploadDirectoryEnum::tryFrom($directory) ?? UploadDirectoryEnum::PROFILE);

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

        return $this->json([
            '@type' => 'MediaObject',
            'contentUrl' => $this->storage->resolveUri($mediaObject, 'file'),
        ], Response::HTTP_CREATED);
    }
}

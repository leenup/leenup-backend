<?php

namespace App\State\Processor\Upload;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Upload\CurrentUserAvatarUpload;
use App\Entity\User;
use App\Enum\UploadDirectory;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<CurrentUserAvatarUpload, CurrentUserAvatarUpload>
 */
final readonly class CurrentUserAvatarUploadProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
        private FileUploader $fileUploader,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CurrentUserAvatarUpload
    {
        if (!$data instanceof CurrentUserAvatarUpload || !$data->file) {
            throw new \InvalidArgumentException('Avatar upload payload is invalid.');
        }

        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            throw new \ApiPlatform\Validator\Exception\ValidationException($violations);
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated.');
        }

        $user->setAvatarUrl($this->fileUploader->upload($data->file, UploadDirectory::PROFILE));
        $user->onPreUpdate();

        $this->entityManager->flush();

        $data->avatarUrl = $user->getAvatarUrl();

        return $data;
    }
}

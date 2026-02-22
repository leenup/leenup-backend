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
use ApiPlatform\Validator\Exception\ValidationException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
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
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation('This value should not be null.', null, [], $data, 'file', null),
            ]));
        }

        $violations = $this->validator->validate($data);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
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

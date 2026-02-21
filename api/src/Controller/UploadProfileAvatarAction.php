<?php

namespace App\Controller;

use App\ApiResource\CurrentUser\CurrentUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final class UploadProfileAvatarAction
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function __invoke(Request $request): CurrentUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new BadRequestHttpException('User not authenticated.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('The "file" field is required and must be a valid uploaded file.');
        }

        $user->setAvatarFile($file);

        $violations = $this->validator->validateProperty($user, 'avatarFile');
        if (count($violations) > 0) {
            throw new UnprocessableEntityHttpException((string) $violations);
        }

        $this->entityManager->flush();

        if ($user->getAvatarFileName() === null) {
            throw new UnprocessableEntityHttpException('Avatar upload failed. Please try again.');
        }

        $user->setAvatarUrl('/upload/profile/' . $user->getAvatarFileName());
        $this->entityManager->flush();

        $dto = new CurrentUser();
        $dto->id = $user->getId();
        $dto->email = $user->getEmail();
        $dto->roles = $user->getRoles();
        $dto->profiles = $user->getProfiles();
        $dto->firstName = $user->getFirstName();
        $dto->lastName = $user->getLastName();
        $dto->avatarUrl = $user->getAvatarUrl();
        $dto->bio = $user->getBio();
        $dto->location = $user->getLocation();
        $dto->timezone = $user->getTimezone();
        $dto->locale = $user->getLocale();
        $dto->birthdate = $user->getBirthdate();
        $dto->languages = $user->getLanguages();
        $dto->exchangeFormat = $user->getExchangeFormat();
        $dto->learningStyles = $user->getLearningStyles();
        $dto->isMentor = $user->getIsMentor();
        $dto->tokenBalance = $user->getTokenBalance();
        $dto->lastLoginAt = $user->getLastLoginAt();
        $dto->createdAt = $user->getCreatedAt();
        $dto->updatedAt = $user->getUpdatedAt();
        $dto->userSkills = $user->getUserSkills()->toArray();

        return $dto;
    }
}

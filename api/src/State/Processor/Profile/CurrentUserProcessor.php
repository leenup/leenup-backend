<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\CurrentUser\CurrentUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<CurrentUser, CurrentUser>
 */
final class CurrentUserProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param CurrentUser $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CurrentUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required to update the profile');
        }

        if ($data->plainPassword !== null) {
            throw new \InvalidArgumentException(
                'Changing password via /me is not allowed. Use /me/change-password instead.'
            );
        }

        $hasChanges = false;

        // Email
        if ($data->email !== null && $data->email !== $user->getEmail()) {
            if (empty($data->email)) {
                throw new ValidationException($this->validator->validate($data->email, [
                    new Assert\NotBlank(message: 'The email cannot be empty'),
                ]));
            }

            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data->email]);
            if ($existingUser && $existingUser->getId() !== $user->getId()) {
                $violations = new ConstraintViolationList([
                    new ConstraintViolation(
                        'This email is already in use',
                        null,
                        [],
                        $data,
                        'email',
                        $data->email
                    )
                ]);
                throw new ValidationException($violations);
            }

            $user->setEmail($data->email);
            $hasChanges = true;
        }

        // FirstName
        if ($data->firstName !== null && $data->firstName !== $user->getFirstName()) {
            $user->setFirstName($data->firstName);
            $hasChanges = true;
        }

        // LastName
        if ($data->lastName !== null && $data->lastName !== $user->getLastName()) {
            $user->setLastName($data->lastName);
            $hasChanges = true;
        }

        // AvatarUrl
        if ($data->avatarUrl !== null && $data->avatarUrl !== $user->getAvatarUrl()) {
            $user->setAvatarUrl($data->avatarUrl);
            $hasChanges = true;
        }

        // Bio
        if ($data->bio !== null && $data->bio !== $user->getBio()) {
            $user->setBio($data->bio);
            $hasChanges = true;
        }

        // Location
        if ($data->location !== null && $data->location !== $user->getLocation()) {
            $user->setLocation($data->location);
            $hasChanges = true;
        }

        // Timezone
        if ($data->timezone !== null && $data->timezone !== $user->getTimezone()) {
            $user->setTimezone($data->timezone);
            $hasChanges = true;
        }

        // Locale
        if ($data->locale !== null && $data->locale !== $user->getLocale()) {
            $user->setLocale($data->locale);
            $hasChanges = true;
        }

        // Birthdate
        if ($data->birthdate !== null && $data->birthdate != $user->getBirthdate()) {
            $user->setBirthdate($data->birthdate);
            $hasChanges = true;
        }

        // Languages
        if ($data->languages !== null && $data->languages !== $user->getLanguages()) {
            $user->setLanguages($data->languages);
            $hasChanges = true;
        }

        // Exchange format
        if ($data->exchangeFormat !== null && $data->exchangeFormat !== $user->getExchangeFormat()) {
            $user->setExchangeFormat($data->exchangeFormat);
            $hasChanges = true;
        }

        // Learning styles
        if ($data->learningStyles !== null && $data->learningStyles !== $user->getLearningStyles()) {
            $user->setLearningStyles($data->learningStyles);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $violations = $this->validator->validate($user);
            if (count($violations) > 0) {
                throw new ValidationException($violations);
            }

            $user->onPreUpdate();
            $this->entityManager->flush();
        }

        // Remapper l'entité vers le DTO retourné
        $data->id = $user->getId();
        $data->email = $user->getEmail();
        $data->roles = $user->getRoles();
        $data->firstName = $user->getFirstName();
        $data->lastName = $user->getLastName();
        $data->avatarUrl = $user->getAvatarUrl();
        $data->bio = $user->getBio();
        $data->location = $user->getLocation();
        $data->timezone = $user->getTimezone();
        $data->locale = $user->getLocale();
        $data->birthdate = $user->getBirthdate();
        $data->languages = $user->getLanguages();
        $data->exchangeFormat = $user->getExchangeFormat();
        $data->learningStyles = $user->getLearningStyles();
        $data->lastLoginAt = $user->getLastLoginAt();
        $data->createdAt = $user->getCreatedAt();
        $data->updatedAt = $user->getUpdatedAt();

        return $data;
    }
}

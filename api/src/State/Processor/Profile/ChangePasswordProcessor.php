<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Dto\ChangePasswordDto;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Processor pour changer le mot de passe de l'utilisateur connecté
 *
 * @implements ProcessorInterface<ChangePasswordDto, ChangePasswordDto|void>
 */
final class ChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param ChangePasswordDto $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if (!$this->passwordHasher->isPasswordValid($currentUser, $data->currentPassword)) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'The current password is incorrect',
                    null,
                    [],
                    $data,
                    'currentPassword',
                    $data->currentPassword
                )
            ]);
            throw new ValidationException($violations);
        }

        if ($this->passwordHasher->isPasswordValid($currentUser, $data->newPassword)) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'The new password must be different from the current password',
                    null,
                    [],
                    $data,
                    'newPassword',
                    $data->newPassword
                )
            ]);
            throw new ValidationException($violations);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($currentUser, $data->newPassword);
        $currentUser->setPassword($hashedPassword);

        $this->entityManager->flush();

        return $data;
    }
}

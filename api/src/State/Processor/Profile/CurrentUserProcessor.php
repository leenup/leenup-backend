<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Processor pour modifier le profil de l'utilisateur connecté
 *
 * @implements ProcessorInterface<User, User|void>
 */
final class CurrentUserProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * @param User $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): User
    {
        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if ($data->getEmail() !== null && $data->getEmail() !== $currentUser->getEmail()) {
            $currentUser->setEmail($data->getEmail());
        }

        $violations = $this->validator->validate($currentUser);
        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        return $this->persistProcessor->process($currentUser, $operation, $uriVariables, $context);
    }
}

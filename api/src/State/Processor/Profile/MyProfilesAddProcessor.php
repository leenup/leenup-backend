<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Profile\MyProfiles;
use App\Entity\User;
use App\Service\CardUnlocker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @implements ProcessorInterface<MyProfiles, MyProfiles>
 */
final class MyProfilesAddProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private CardUnlocker $cardUnlocker,
    ) {
    }

    /**
     * @param MyProfiles $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyProfiles
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $profiles = $user->getProfiles();
        $profile = $data->profile;

        if ($profile !== null && !in_array($profile, $profiles, true)) {
            $profiles[] = $profile;
            $user->setProfiles($profiles);

            $violations = $this->validator->validate($user);
            if (count($violations) > 0) {
                throw new ValidationException($violations);
            }

            $user->onPreUpdate();
            $this->cardUnlocker->unlockForUser($user, 'profile_updated');
            $this->entityManager->flush();
        }

        $data->profiles = $user->getProfiles();

        return $data;
    }
}

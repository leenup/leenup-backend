<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyMentorAvailability;
use App\Entity\MentorAvailability;
use App\Entity\User;
use App\Repository\MentorAvailabilityRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MyMentorAvailability>
 */
final class MyMentorAvailabilityProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityRepository $availabilityRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if (isset($uriVariables['id'])) {
            $availability = $this->availabilityRepository->find($uriVariables['id']);

            if (!$availability || $availability->getMentor()?->getId() !== $user->getId()) {
                throw new NotFoundHttpException('Availability not found');
            }

            return $this->mapToDto($availability);
        }

        $availabilities = $this->availabilityRepository->findBy(['mentor' => $user]);

        return array_map(fn(MentorAvailability $availability) => $this->mapToDto($availability), $availabilities);
    }

    private function mapToDto(MentorAvailability $availability): MyMentorAvailability
    {
        $dto = new MyMentorAvailability();
        $dto->id = $availability->getId();
        $dto->dayOfWeek = $availability->getDayOfWeek();
        $dto->startTime = $availability->getStartTime();
        $dto->endTime = $availability->getEndTime();
        $dto->createdAt = $availability->getCreatedAt();
        $dto->updatedAt = $availability->getUpdatedAt();

        return $dto;
    }
}

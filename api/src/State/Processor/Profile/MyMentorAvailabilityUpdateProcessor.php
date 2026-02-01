<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\MyMentorAvailability;
use App\Entity\User;
use App\Repository\MentorAvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MyMentorAvailability, MyMentorAvailability>
 */
final class MyMentorAvailabilityUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityRepository $availabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyMentorAvailability
    {
        if (!$data instanceof MyMentorAvailability) {
            throw new \LogicException('Expected MyMentorAvailability data');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $availability = $this->availabilityRepository->find($uriVariables['id'] ?? null);

        if (!$availability || $availability->getMentor()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Availability not found');
        }

        if ($data->dayOfWeek !== null) {
            $availability->setDayOfWeek($data->dayOfWeek);
        }

        if ($data->startTime !== null) {
            $availability->setStartTime($data->startTime);
        }

        if ($data->endTime !== null) {
            $availability->setEndTime($data->endTime);
        }

        $this->entityManager->flush();

        $data->id = $availability->getId();
        $data->createdAt = $availability->getCreatedAt();
        $data->updatedAt = $availability->getUpdatedAt();

        return $data;
    }
}

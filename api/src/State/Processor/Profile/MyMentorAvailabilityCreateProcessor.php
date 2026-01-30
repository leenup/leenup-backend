<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\MyMentorAvailability;
use App\Entity\MentorAvailability;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MyMentorAvailability, MyMentorAvailability>
 */
final class MyMentorAvailabilityCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
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

        $availability = new MentorAvailability();
        $availability->setMentor($user);
        $availability->setDayOfWeek($data->dayOfWeek ?? 0);
        $availability->setStartTime($data->startTime ?? new \DateTimeImmutable('00:00'));
        $availability->setEndTime($data->endTime ?? new \DateTimeImmutable('00:00'));

        $this->entityManager->persist($availability);
        $this->entityManager->flush();

        $data->id = $availability->getId();
        $data->createdAt = $availability->getCreatedAt();
        $data->updatedAt = $availability->getUpdatedAt();

        return $data;
    }
}

<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\MyMentorAvailabilityException;
use App\Entity\MentorAvailabilityException;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<MyMentorAvailabilityException, MyMentorAvailabilityException>
 */
final class MyMentorAvailabilityExceptionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyMentorAvailabilityException
    {
        if (!$data instanceof MyMentorAvailabilityException) {
            throw new \LogicException('Expected MyMentorAvailabilityException data');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $exception = new MentorAvailabilityException();
        $exception->setMentor($user);
        $exception->setDate($data->date ?? new \DateTimeImmutable('today'));
        $exception->setStartTime($data->startTime);
        $exception->setEndTime($data->endTime);
        $exception->setType($data->type ?? MentorAvailabilityException::TYPE_UNAVAILABLE);

        $this->entityManager->persist($exception);
        $this->entityManager->flush();

        $data->id = $exception->getId();
        $data->createdAt = $exception->getCreatedAt();
        $data->updatedAt = $exception->getUpdatedAt();

        return $data;
    }
}

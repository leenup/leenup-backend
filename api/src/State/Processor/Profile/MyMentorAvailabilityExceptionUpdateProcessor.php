<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\MyMentorAvailabilityException;
use App\Entity\User;
use App\Repository\MentorAvailabilityExceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MyMentorAvailabilityException, MyMentorAvailabilityException>
 */
final class MyMentorAvailabilityExceptionUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityExceptionRepository $exceptionRepository,
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

        $exception = $this->exceptionRepository->find($uriVariables['id'] ?? null);

        if (!$exception || $exception->getMentor()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Availability exception not found');
        }

        if ($data->date !== null) {
            $exception->setDate($data->date);
        }

        if ($data->startTime !== null) {
            $exception->setStartTime($data->startTime);
        }

        if ($data->endTime !== null) {
            $exception->setEndTime($data->endTime);
        }

        if ($data->type !== null) {
            $exception->setType($data->type);
        }

        $this->entityManager->flush();

        $data->id = $exception->getId();
        $data->createdAt = $exception->getCreatedAt();
        $data->updatedAt = $exception->getUpdatedAt();

        return $data;
    }
}

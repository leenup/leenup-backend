<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyMentorAvailabilityException;
use App\Entity\MentorAvailabilityException;
use App\Entity\User;
use App\Repository\MentorAvailabilityExceptionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<MyMentorAvailabilityException>
 */
final class MyMentorAvailabilityExceptionProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityExceptionRepository $exceptionRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if (isset($uriVariables['id'])) {
            $exception = $this->exceptionRepository->find($uriVariables['id']);

            if (!$exception || $exception->getMentor()?->getId() !== $user->getId()) {
                throw new NotFoundHttpException('Availability exception not found');
            }

            return $this->mapToDto($exception);
        }

        $exceptions = $this->exceptionRepository->findBy(['mentor' => $user]);

        return array_map(fn(MentorAvailabilityException $exception) => $this->mapToDto($exception), $exceptions);
    }

    private function mapToDto(MentorAvailabilityException $exception): MyMentorAvailabilityException
    {
        $dto = new MyMentorAvailabilityException();
        $dto->id = $exception->getId();
        $dto->date = $exception->getDate();
        $dto->startTime = $exception->getStartTime();
        $dto->endTime = $exception->getEndTime();
        $dto->type = $exception->getType();
        $dto->createdAt = $exception->getCreatedAt();
        $dto->updatedAt = $exception->getUpdatedAt();

        return $dto;
    }
}

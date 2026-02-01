<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\MentorAvailabilityExceptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProcessorInterface<MyMentorAvailabilityException, void>
 */
final class MyMentorAvailabilityExceptionDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityExceptionRepository $exceptionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $exception = $this->exceptionRepository->find($uriVariables['id'] ?? null);

        if (!$exception || $exception->getMentor()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Availability exception not found');
        }

        $this->entityManager->remove($exception);
        $this->entityManager->flush();
    }
}

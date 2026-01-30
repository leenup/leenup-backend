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
 * @implements ProcessorInterface<MyMentorAvailability, void>
 */
final class MyMentorAvailabilityDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private MentorAvailabilityRepository $availabilityRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $availability = $this->availabilityRepository->find($uriVariables['id'] ?? null);

        if (!$availability || $availability->getMentor()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Availability not found');
        }

        $this->entityManager->remove($availability);
        $this->entityManager->flush();
    }
}

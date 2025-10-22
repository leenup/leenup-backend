<?php

namespace App\State\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Review, Review>
 */
final class ReviewUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Review
    {
        if (!$data instanceof Review) {
            throw new \LogicException('Expected Review entity');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // Admins peuvent toujours modifier
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $this->entityManager->flush();
            return $data;
        }

        // Vérifier que c'est le reviewer
        if ($data->getReviewer() !== $currentUser) {
            throw new AccessDeniedHttpException('You can only modify your own reviews');
        }

        // Vérifier la limite de 7 jours
        $createdAt = $data->getCreatedAt();
        $sevenDaysAgo = new \DateTimeImmutable('-7 days');

        if ($createdAt < $sevenDaysAgo) {
            $violations = new ConstraintViolationList([
                new \Symfony\Component\Validator\ConstraintViolation(
                    'You can only modify a review within 7 days of creation',
                    null,
                    [],
                    $data,
                    '',
                    null
                )
            ]);
            throw new ValidationException($violations);
        }

        $this->entityManager->flush();

        return $data;
    }
}

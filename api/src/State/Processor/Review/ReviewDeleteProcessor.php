<?php

namespace App\State\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Review;
use App\Service\MentorRatingUpdater;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<Review, null>
 */
final class ReviewDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MentorRatingUpdater $mentorRatingUpdater,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof Review) {
            throw new \LogicException('Expected Review entity');
        }

        $mentor = $data->getSession()?->getMentor();

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        if ($mentor !== null) {
            $this->mentorRatingUpdater->update($mentor);
            $this->entityManager->flush();
        }

        return null;
    }
}

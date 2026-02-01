<?php

namespace App\State\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProcessorInterface<Review, Review>
 */
final class ReviewUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Review
    {
        if (!$data instanceof Review) {
            throw new \LogicException('Expected Review entity');
        }

        $this->entityManager->flush();

        return $data;
    }
}

<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyReviewsGiven;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @implements ProviderInterface<MyReviewsGiven>
 */
final class MyReviewsGivenProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private ReviewRepository $reviewRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required to view given reviews');
        }

        $reviews = $this->reviewRepository->findBy(
            ['reviewer' => $user],
            ['createdAt' => 'DESC']
        );

        return array_map(fn(Review $review) => $this->mapToDto($review), $reviews);
    }

    private function mapToDto(Review $review): MyReviewsGiven
    {
        $dto = new MyReviewsGiven();
        $dto->id = $review->getId();
        $dto->session = $review->getSession();
        $dto->rating = $review->getRating();
        $dto->comment = $review->getComment();
        $dto->createdAt = $review->getCreatedAt();
        $dto->updatedAt = $review->getUpdatedAt();

        return $dto;
    }
}

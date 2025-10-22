<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyReviewsReceived;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MyReviewsReceived>
 */
final class MyReviewsReceivedProvider implements ProviderInterface
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
            throw new \LogicException('User not authenticated');
        }

        // Récupérer les reviews où l'utilisateur est le mentor de la session
        $reviews = $this->reviewRepository->createQueryBuilder('r')
            ->join('r.session', 's')
            ->where('s.mentor = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(fn(Review $review) => $this->mapToDto($review), $reviews);
    }

    private function mapToDto(Review $review): MyReviewsReceived
    {
        $dto = new MyReviewsReceived();
        $dto->id = $review->getId();
        $dto->session = $review->getSession();
        $dto->reviewer = $review->getReviewer();
        $dto->rating = $review->getRating();
        $dto->comment = $review->getComment();
        $dto->createdAt = $review->getCreatedAt();
        $dto->updatedAt = $review->getUpdatedAt();

        return $dto;
    }
}

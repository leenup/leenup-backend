<?php

namespace App\State\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Service\CardUnlocker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Review, Review>
 */
final class ReviewCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ReviewRepository $reviewRepository,
        private CardUnlocker $cardUnlocker,
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

        // Auto-set reviewer = currentUser
        $data->setReviewer($currentUser);

        $session = $data->getSession();

        if (!$session instanceof Session) {
            throw new \LogicException('Session not found');
        }

        // Validation 1 : La session doit être completed
        if ($session->getStatus() !== Session::STATUS_COMPLETED) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'You can only review a completed session',
                    null,
                    [],
                    $data,
                    'session',
                    $session
                ),
            ]));
        }

        // Validation 2 : Le reviewer doit être le student de la session (comparaison par ID)
        if ($session->getStudent()?->getId() !== $currentUser->getId()) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'You can only review sessions where you are the student',
                    null,
                    [],
                    $data,
                    'session',
                    $session
                ),
            ]));
        }

        // Validation 3 : Vérifier qu'il n'y a pas déjà une review pour cette session par ce reviewer
        $existingReview = $this->reviewRepository->findOneBy([
            'session' => $session,
            'reviewer' => $currentUser,
        ]);

        if ($existingReview !== null) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'You have already reviewed this session',
                    null,
                    [],
                    $data,
                    'session',
                    $session
                ),
            ]));
        }

        $this->entityManager->persist($data);

        $this->updateMentorAverageRating($session->getMentor());
        $this->cardUnlocker->unlockForUser($session->getMentor(), 'review_received', [
            'sessionId' => $session->getId(),
        ]);
        $this->entityManager->flush();

        return $data;
    }

    private function updateMentorAverageRating(User $mentor): void
    {
        $reviews = $this->reviewRepository->createQueryBuilder('r')
            ->join('r.session', 's')
            ->where('s.mentor = :mentor')
            ->setParameter('mentor', $mentor)
            ->getQuery()
            ->getResult();

        if (count($reviews) === 0) {
            $mentor->setAverageRating(null);
            return;
        }

        $totalRating = 0;
        foreach ($reviews as $review) {
            $totalRating += $review->getRating();
        }

        $average = $totalRating / count($reviews);
        $mentor->setAverageRating(number_format($average, 2, '.', ''));
    }
}

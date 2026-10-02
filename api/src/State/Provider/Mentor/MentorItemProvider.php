<?php

namespace App\State\Provider\Mentor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Mentor\Mentor;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;

/**
 * GET /mentors/{id} : profil public d'un mentor avec ses dernières reviews.
 * Renvoie 404 si l'utilisateur n'existe pas, est inactif ou n'enseigne aucune compétence.
 *
 * @implements ProviderInterface<Mentor>
 */
final class MentorItemProvider implements ProviderInterface
{
    public const RECENT_REVIEWS_LIMIT = 5;

    public function __construct(
        private UserRepository $userRepository,
        private ReviewRepository $reviewRepository,
        private MentorMapper $mentorMapper,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Mentor
    {
        $id = filter_var($uriVariables['id'] ?? null, FILTER_VALIDATE_INT);
        $user = $id === false ? null : $this->userRepository->findOneMentor($id);

        if ($user === null) {
            return null;
        }

        $mentor = $this->mentorMapper->toMentor($user);
        $mentor->recentReviews = array_map(
            $this->mentorMapper->toMentorReview(...),
            $this->reviewRepository->findRecentForMentor($user, self::RECENT_REVIEWS_LIMIT),
        );

        return $mentor;
    }
}

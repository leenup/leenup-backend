<?php

namespace App\State\Processor\Review;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Review;
use App\Entity\User;
use App\Security\Voter\ReviewVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @implements ProcessorInterface<Review, Review>
 */
final class ReviewUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AuthorizationCheckerInterface $authChecker,
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

        // Vérification via le Voter : l'utilisateur peut-il modifier cette review ?
        // Le Voter vérifie automatiquement :
        // 1. Que c'est bien le reviewer
        // 2. Que la review a moins de 7 jours
        if (!$this->authChecker->isGranted(ReviewVoter::UPDATE, $data)) {
            throw new AccessDeniedHttpException(
                'You can only modify your own reviews within 7 days of creation'
            );
        }

        // Les admins peuvent toujours modifier (bypass du Voter)
        // Note : Cette logique pourrait aussi être dans le Voter si besoin

        $this->entityManager->flush();

        return $data;
    }
}

<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Entity\Review;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class ReviewNotificationSubscriber
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    /**
     * Appelé après la création d'une entité
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Review) {
            return;
        }

        $this->handleReviewCreated($entity);
    }

    /**
     * Gère la notification lors de la création d'une review
     */
    private function handleReviewCreated(Review $review): void
    {
        $session = $review->getSession();
        $mentor = $session->getMentor();
        $reviewer = $review->getReviewer();

        // Notifier le mentor qu'il a reçu un avis
        $this->notificationService->createNotification(
            user: $mentor,
            type: Notification::TYPE_NEW_REVIEW,
            title: 'Nouvel avis reçu',
            content: sprintf(
                '%s %s a laissé un avis %d/5 sur votre session de "%s"',
                $reviewer->getFirstName(),
                $reviewer->getLastName(),
                $review->getRating(),
                $session->getSkill()->getTitle()
            ),
            link: '/reviews/' . $review->getId()
        );
    }
}

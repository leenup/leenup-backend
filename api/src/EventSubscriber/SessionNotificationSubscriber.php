<?php

namespace App\EventSubscriber;

use App\Entity\Notification;
use App\Entity\Session;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class SessionNotificationSubscriber
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

        if (!$entity instanceof Session) {
            return;
        }

        $this->handleSessionCreated($entity);
    }

    /**
     * Appelé après la mise à jour d'une entité
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Session) {
            return;
        }

        $this->handleSessionStatusChanged($entity, $args);
    }

    /**
     * Gère la notification lors de la création d'une session
     */
    private function handleSessionCreated(Session $session): void
    {
        // Notifier le mentor qu'un student a créé une session
        $this->notificationService->createNotification(
            user: $session->getMentor(),
            type: Notification::TYPE_SESSION_REQUESTED, // ← Change ici
            title: 'Nouvelle demande de session',
            content: sprintf(
                '%s %s a demandé une session avec vous pour "%s" le %s',
                $session->getStudent()->getFirstName(),
                $session->getStudent()->getLastName(),
                $session->getSkill()->getTitle(),
                $session->getScheduledAt()->format('d/m/Y à H:i')
            ),
            link: '/sessions/' . $session->getId()
        );
    }

    /**
     * Gère les notifications lors du changement de statut
     */
    private function handleSessionStatusChanged(Session $session, PostUpdateEventArgs $args): void
    {
        $changeSet = $args->getObjectManager()
            ->getUnitOfWork()
            ->getEntityChangeSet($session);

        // Si le statut n'a pas changé, on ne fait rien
        if (!isset($changeSet['status'])) {
            return;
        }

        [$oldStatus, $newStatus] = $changeSet['status'];

        match ($newStatus) {
            Session::STATUS_CONFIRMED => $this->notifySessionConfirmed($session),
            Session::STATUS_CANCELLED => $this->notifySessionCancelled($session),
            Session::STATUS_COMPLETED => $this->notifySessionCompleted($session),
            default => null,
        };
    }

    /**
     * Notification quand la session est confirmée
     */
    private function notifySessionConfirmed(Session $session): void
    {
        // Notifier le student que le mentor a confirmé
        $this->notificationService->createNotification(
            user: $session->getStudent(),
            type: Notification::TYPE_SESSION_CONFIRMED,
            title: 'Session confirmée',
            content: sprintf(
                '%s %s a confirmé votre session de "%s" prévue le %s',
                $session->getMentor()->getFirstName(),
                $session->getMentor()->getLastName(),
                $session->getSkill()->getTitle(),
                $session->getScheduledAt()->format('d/m/Y à H:i')
            ),
            link: '/sessions/' . $session->getId()
        );
    }

    /**
     * Notification quand la session est annulée
     */
    private function notifySessionCancelled(Session $session): void
    {
        // Déterminer qui a annulé et notifier l'autre
        // On ne peut pas savoir qui a annulé, donc on notifie les deux
        // (sauf que normalement, celui qui annule sait déjà...)

        // Notifier le student
        $this->notificationService->createNotification(
            user: $session->getStudent(),
            type: Notification::TYPE_SESSION_CANCELLED,
            title: 'Session annulée',
            content: sprintf(
                'Votre session de "%s" avec %s %s a été annulée',
                $session->getSkill()->getTitle(),
                $session->getMentor()->getFirstName(),
                $session->getMentor()->getLastName()
            ),
            link: '/sessions/' . $session->getId()
        );

        // Notifier le mentor
        $this->notificationService->createNotification(
            user: $session->getMentor(),
            type: Notification::TYPE_SESSION_CANCELLED,
            title: 'Session annulée',
            content: sprintf(
                'Votre session de "%s" avec %s %s a été annulée',
                $session->getSkill()->getTitle(),
                $session->getStudent()->getFirstName(),
                $session->getStudent()->getLastName()
            ),
            link: '/sessions/' . $session->getId()
        );
    }

    /**
     * Notification quand la session est complétée
     */
    private function notifySessionCompleted(Session $session): void
    {
        // Notifier le student qu'il peut laisser un avis
        $this->notificationService->createNotification(
            user: $session->getStudent(),
            type: Notification::TYPE_SESSION_COMPLETED,
            title: 'Session terminée',
            content: sprintf(
                'Votre session de "%s" avec %s %s est terminée. Vous pouvez maintenant laisser un avis.',
                $session->getSkill()->getTitle(),
                $session->getMentor()->getFirstName(),
                $session->getMentor()->getLastName()
            ),
            link: '/sessions/' . $session->getId()
        );
    }
}

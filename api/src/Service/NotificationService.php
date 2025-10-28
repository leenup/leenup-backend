<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
    ) {
    }

    /**
     * Crée et persiste une nouvelle notification
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        ?string $content = null,
        ?string $link = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setContent($content);
        $notification->setLink($link);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->entityManager->flush();
        }
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $unreadNotifications = $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ]);

        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     */
    public function getUnreadNotifications(User $user): array
    {
        return $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Récupère toutes les notifications d'un utilisateur (lues et non lues)
     */
    public function getAllNotifications(User $user, int $limit = 50): array
    {
        return $this->notificationRepository->findBy([
            'user' => $user,
        ], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Compte le nombre de notifications non lues d'un utilisateur
     */
    public function countUnread(User $user): int
    {
        return $this->notificationRepository->count([
            'user' => $user,
            'isRead' => false,
        ]);
    }

    /**
     * Supprime les anciennes notifications (par exemple > 30 jours)
     * Utile pour un cleanup automatique via une commande Symfony
     */
    public function deleteOldNotifications(\DateTimeImmutable $olderThan): int
    {
        return $this->notificationRepository->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $olderThan)
            ->getQuery()
            ->execute();
    }
}

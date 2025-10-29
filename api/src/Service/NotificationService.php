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
     * Marque toutes les notifications non lues d'un utilisateur comme lues
     */
    public function markAllAsReadForUser(User $user): int
    {
        $notifications = $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
        ]);

        $count = 0;
        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
            $count++;
        }

        $this->entityManager->flush();

        return $count;
    }
}

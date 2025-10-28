<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyNotifications;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Provider pour récupérer les notifications de l'utilisateur connecté
 */
class MyNotificationsProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
    ) {
    }

    /**
     * @return MyNotifications|MyNotifications[]|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        // Si c'est une collection (GET /me/notifications)
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            $notifications = $this->notificationRepository->findBy(
                ['user' => $user],
                ['createdAt' => 'DESC']
            );

            return array_map(
                fn(Notification $notification) => $this->mapToDto($notification),
                $notifications
            );
        }

        // Si c'est un item (GET /me/notifications/{id} ou PATCH)
        if (isset($uriVariables['id'])) {
            $notification = $this->notificationRepository->findOneBy([
                'id' => $uriVariables['id'],
                'user' => $user,
            ]);

            if (!$notification) {
                return null;
            }

            return $this->mapToDto($notification);
        }

        return null;
    }

    /**
     * Convertit une entité Notification en DTO MyNotifications
     */
    private function mapToDto(Notification $notification): MyNotifications
    {
        $dto = new MyNotifications();
        $dto->id = $notification->getId();
        $dto->type = $notification->getType();
        $dto->title = $notification->getTitle();
        $dto->content = $notification->getContent();
        $dto->link = $notification->getLink();
        $dto->isRead = $notification->isRead();
        $dto->readAt = $notification->getReadAt();
        $dto->createdAt = $notification->getCreatedAt();

        return $dto;
    }
}

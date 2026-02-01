<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MyNotifications;
use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider pour récupérer les notifications de l'utilisateur connecté
 */
class MyNotificationsProvider implements ProviderInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private Security $security,
        private RequestStack $requestStack,
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
            $request = $this->requestStack->getCurrentRequest();

            // Construire les critères de filtrage
            $criteria = ['user' => $user];
            $orderBy = ['createdAt' => 'DESC']; // Par défaut, du plus récent au plus ancien

            // Filtre isRead (GET /me/notifications?isRead=false)
            if ($request && $request->query->has('isRead')) {
                $isRead = filter_var($request->query->get('isRead'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isRead !== null) {
                    $criteria['isRead'] = $isRead;
                }
            }

            // Filtre type (GET /me/notifications?type=new_message)
            if ($request && $request->query->has('type')) {
                $criteria['type'] = $request->query->get('type');
            }

            // Order (GET /me/notifications?order[createdAt]=asc)
            if ($request && $request->query->has('order')) {
                $orderParam = $request->query->all('order');
                $orderBy = [];

                foreach ($orderParam as $field => $direction) {
                    if (in_array($field, ['createdAt', 'readAt']) && in_array($direction, ['asc', 'desc'])) {
                        $orderBy[$field] = strtoupper($direction);
                    }
                }

                // Si aucun order valide, garder le défaut
                if (empty($orderBy)) {
                    $orderBy = ['createdAt' => 'DESC'];
                }
            }

            $notifications = $this->notificationRepository->findBy($criteria, $orderBy);

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

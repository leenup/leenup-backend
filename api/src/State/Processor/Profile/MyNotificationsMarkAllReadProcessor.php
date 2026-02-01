<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Processor pour marquer toutes les notifications comme lues
 */
class MyNotificationsMarkAllReadProcessor implements ProcessorInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'User not authenticated'], 401);
        }

        $count = $this->notificationService->markAllAsReadForUser($user);

        return new JsonResponse([
            'message' => sprintf('%d notification(s) marked as read', $count),
            'count' => $count,
        ], 200);
    }
}

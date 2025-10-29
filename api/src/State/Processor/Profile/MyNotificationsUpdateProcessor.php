<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Profile\MyNotifications;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor pour mettre à jour les notifications de l'utilisateur connecté
 */
class MyNotificationsUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @param MyNotifications $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MyNotifications
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        // Récupérer la notification
        $notification = $this->notificationRepository->findOneBy([
            'id' => $uriVariables['id'],
            'user' => $user,
        ]);

        if (!$notification) {
            throw new NotFoundHttpException('Notification not found');
        }

        // Mettre à jour isRead (seul champ modifiable)
        if ($data->isRead !== null) {
            $notification->setIsRead($data->isRead);
        }

        $this->entityManager->flush();

        // Retourner le DTO mis à jour
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

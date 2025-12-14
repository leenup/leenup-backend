<?php

namespace App\EventSubscriber;

use App\Entity\Message;
use App\Entity\Notification;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
class MessageNotificationSubscriber
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Message) {
            return;
        }

        $message = $entity;
        $conversation = $message->getConversation();
        $sender = $message->getSender();

        if ($conversation === null || $sender === null) {
            return;
        }

        $senderId = $sender->getId();

        if ($senderId === null) {
            return;
        }

        $recipient = $conversation->getParticipant1()?->getId() === $senderId
            ? $conversation->getParticipant2()
            : $conversation->getParticipant1();

        if ($recipient === null) {
            return;
        }

        $this->notificationService->createNotification(
            user: $recipient,
            type: Notification::TYPE_NEW_MESSAGE,
            title: 'Nouveau message',
            content: sprintf(
                'Vous avez reçu un message de %s %s',
                $sender->getFirstName(),
                $sender->getLastName()
            ),
            link: '/conversations/' . $conversation->getId()
        );
    }
}

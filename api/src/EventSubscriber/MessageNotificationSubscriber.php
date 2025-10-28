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

        // On ne s'intéresse qu'aux entités Message
        if (!$entity instanceof Message) {
            return;
        }

        $message = $entity;
        $conversation = $message->getConversation();
        $sender = $message->getSender();

        // Déterminer qui est le destinataire (l'autre participant)
        $recipient = $conversation->getParticipant1() === $sender
            ? $conversation->getParticipant2()
            : $conversation->getParticipant1();

        // Créer la notification pour le destinataire
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

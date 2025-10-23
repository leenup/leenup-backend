<?php

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Message, Message>
 */
final class MessageCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Message
    {
        if (!$data instanceof Message) {
            throw new \LogicException('Expected Message entity');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // Auto-set sender = currentUser
        $data->setSender($currentUser);

        // Auto-set read = false
        $data->setRead(false);

        $conversation = $data->getConversation();

        // Validation : Vérifier que l'utilisateur est participant de la conversation
        if ($conversation->getParticipant1() !== $currentUser && $conversation->getParticipant2() !== $currentUser) {
            throw new AccessDeniedHttpException('You can only send messages in your own conversations');
        }

        // Mettre à jour lastMessageAt de la conversation
        $conversation->setLastMessageAt(new \DateTimeImmutable());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}

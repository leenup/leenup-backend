<?php

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message;
use App\Entity\User;
use App\Security\Voter\MessageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @implements ProcessorInterface<Message, Message>
 */
final class MessageCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private AuthorizationCheckerInterface $authChecker,
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

        if (!$this->authChecker->isGranted(MessageVoter::CREATE, $data)) {
            throw new AccessDeniedHttpException(
                'You can only send messages in conversations you are part of'
            );
        }

        $data->setSender($currentUser);

        $data->setRead(false);

        $conversation = $data->getConversation();

        if ($conversation) {
            $conversation->setLastMessageAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}

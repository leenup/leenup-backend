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
final class MessageUpdateProcessor implements ProcessorInterface
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

        $uow = $this->entityManager->getUnitOfWork();

        $uow->computeChangeSets();

        $changeSet = $uow->getEntityChangeSet($data);

        if (isset($changeSet['content'])) {
            if (!$this->authChecker->isGranted(MessageVoter::UPDATE, $data)) {
                throw new AccessDeniedHttpException(
                    'You cannot modify the content of a message'
                );
            }
        }

        if (isset($changeSet['read'])) {
            if (!$this->authChecker->isGranted(MessageVoter::MARK_READ, $data)) {
                throw new AccessDeniedHttpException(
                    'Only the recipient can mark a message as read'
                );
            }
        }

        $this->entityManager->flush();

        return $data;
    }
}

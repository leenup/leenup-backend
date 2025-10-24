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

        // 🔴 CRITIQUE : Détecter si l'utilisateur essaie de modifier le contenu
        // Le seul champ modifiable devrait être "read"

        // Récupérer les données originales pour détecter les changements
        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($data);

        // Si le contenu a été modifié, c'est une tentative d'UPDATE
        if (isset($originalData['content']) && $originalData['content'] !== $data->getContent()) {
            if (!$this->authChecker->isGranted(MessageVoter::UPDATE, $data)) {
                throw new AccessDeniedHttpException(
                    'You cannot modify the content of a message'
                );
            }
        }

        // Si le champ "read" a été modifié, c'est une tentative de MARK_READ
        if (isset($originalData['read']) && $originalData['read'] !== $data->isRead()) {
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

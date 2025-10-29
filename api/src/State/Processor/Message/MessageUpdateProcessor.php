<?php

namespace App\State\Processor\Message;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Security\Voter\MessageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        private MessageRepository $messageRepository,
        private RequestStack $requestStack,
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

        // 🔒 VÉRIFICATION IMMÉDIATE : Bloquer les tentatives de modification de champs interdits
        // Cette vérification se fait AVANT toute interaction avec la base de données
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $requestData = json_decode($request->getContent(), true);

            if (isset($requestData['sender'])) {
                // Clear pour annuler TOUTES les modifications trackées par Doctrine
                $this->entityManager->clear();
                throw new AccessDeniedHttpException(
                    'You cannot modify the sender of a message'
                );
            }

            if (isset($requestData['conversation'])) {
                // Clear pour annuler TOUTES les modifications trackées par Doctrine
                $this->entityManager->clear();
                throw new AccessDeniedHttpException(
                    'You cannot modify the conversation of a message'
                );
            }
        }

        // Récupérer le message original depuis la BDD pour comparer
        $originalMessage = $this->messageRepository->find($data->getId());

        if (!$originalMessage) {
            throw new NotFoundHttpException('Message not found');
        }

        $uow = $this->entityManager->getUnitOfWork();
        $uow->computeChangeSets();
        $changeSet = $uow->getEntityChangeSet($data);

        // 🔐 VÉRIFICATION DES PERMISSIONS pour les modifications autorisées

        if (isset($changeSet['content'])) {
            if (!$this->authChecker->isGranted(MessageVoter::UPDATE, $originalMessage)) {
                // Clear pour annuler les modifications
                $this->entityManager->clear();
                throw new AccessDeniedHttpException(
                    'You cannot modify the content of a message'
                );
            }
        }

        if (isset($changeSet['read'])) {
            if (!$this->authChecker->isGranted(MessageVoter::MARK_READ, $originalMessage)) {
                $this->entityManager->clear();
                throw new AccessDeniedHttpException(
                    'Only the recipient can mark a message as read'
                );
            }
        }

        $this->entityManager->flush();

        return $data;
    }
}

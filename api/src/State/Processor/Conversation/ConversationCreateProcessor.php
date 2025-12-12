<?php

namespace App\State\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Conversation, Conversation>
 */
final class ConversationCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ConversationRepository $conversationRepository,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Conversation
    {
        if (!$data instanceof Conversation) {
            throw new \LogicException('Expected Conversation entity');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required to create a conversation');
        }

        // Auto-set participant1 = currentUser
        $data->setParticipant1($currentUser);

        $participant2 = $data->getParticipant2();

        // Validation 1 : On ne peut pas créer une conversation avec soi-même
        if ($participant2 === $currentUser) {
            $violations = new ConstraintViolationList([
                new \Symfony\Component\Validator\ConstraintViolation(
                    'You cannot create a conversation with yourself',
                    null,
                    [],
                    $data,
                    'participant2',
                    $participant2
                )
            ]);
            throw new ValidationException($violations);
        }

        // Validation 2 : Vérifier qu'il n'existe pas déjà une conversation entre ces 2 users
        $existingConversation = $this->conversationRepository->findConversationBetweenUsers(
            $currentUser,
            $participant2
        );

        if ($existingConversation) {
            $violations = new ConstraintViolationList([
                new \Symfony\Component\Validator\ConstraintViolation(
                    'A conversation already exists with this user',
                    null,
                    [],
                    $data,
                    'participant2',
                    $participant2
                )
            ]);
            throw new ValidationException($violations);
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}

<?php

namespace App\State\Processor\Conversation;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Conversation;
use App\Entity\User;
use App\Repository\ConversationRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
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
            throw new \LogicException('User not authenticated');
        }

        $data->setParticipant1($currentUser);

        $participant2 = $data->getParticipant2();

        if ($participant2 === $currentUser) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
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

        $data->normalizeParticipants();

        $existingConversation = $this->conversationRepository->findConversationBetweenUsers(
            $data->getParticipant1(),
            $data->getParticipant2()
        );

        if ($existingConversation) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
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

        try {
            $this->entityManager->persist($data);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
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

        return $data;
    }
}

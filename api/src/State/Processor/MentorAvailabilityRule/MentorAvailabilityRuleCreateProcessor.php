<?php

namespace App\State\Processor\MentorAvailabilityRule;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\MentorAvailabilityRule;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<MentorAvailabilityRule, MentorAvailabilityRule>
 */
final class MentorAvailabilityRuleCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MentorAvailabilityRule
    {
        if (!$data instanceof MentorAvailabilityRule) {
            throw new \LogicException('Expected MentorAvailabilityRule entity');
        }

        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if (!$currentUser->isMentor()) {
            throw new ValidationException(new ConstraintViolationList([
                new ConstraintViolation(
                    'Only mentors can create availability rules',
                    null,
                    [],
                    $data,
                    'mentor',
                    null
                ),
            ]));
        }

        $data->setMentor($currentUser);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}

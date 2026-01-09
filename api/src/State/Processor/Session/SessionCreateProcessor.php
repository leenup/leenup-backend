<?php

namespace App\State\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Session;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Repository\UserSkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @implements ProcessorInterface<Session, Session>
 */
final class SessionCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserSkillRepository $userSkillRepository,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Session
    {
        if (!$data instanceof Session) {
            throw new \LogicException('Expected Session entity');
        }

        $currentUser = $this->security->getUser();

        if (!$currentUser instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        if ($currentUser->getTokenBalance() < 1) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'You need at least 1 token to join a session as a student',
                    null,
                    [],
                    $data,
                    'student',
                    $data->getStudent()
                )
            ]);
            throw new ValidationException($violations);
        }

        // FORCER student = currentUser (ignorer le payload)
        $data->setStudent($currentUser);

        if ($data->getMentor() === $data->getStudent()) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'You cannot be your own mentor',
                    null,
                    [],
                    $data,
                    'mentor',
                    $data->getMentor()
                )
            ]);
            throw new ValidationException($violations);
        }

        $mentorSkill = $this->userSkillRepository->findOneBy([
            'owner' => $data->getMentor(),
            'skill' => $data->getSkill(),
            'type' => UserSkill::TYPE_TEACH,
        ]);

        if (!$mentorSkill) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'The mentor must have this skill with type "teach"',
                    null,
                    [],
                    $data,
                    'mentor',
                    $data->getMentor()
                )
            ]);
            throw new ValidationException($violations);
        }

        $currentUser->removeTokenBalance(1);
        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}

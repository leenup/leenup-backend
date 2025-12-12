<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\UserSkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Processor pour supprimer une compétence de l'utilisateur connecté
 *
 * @implements ProcessorInterface<mixed, void>
 */
final class MySkillsRemoveProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserSkillRepository $userSkillRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Authentication is required to delete a skill');
        }

        $userSkill = $this->userSkillRepository->find($uriVariables['id']);

        if (!$userSkill || $userSkill->getOwner()->getId() !== $user->getId()) {
            throw new NotFoundHttpException('UserSkill not found or you do not have permission to delete it');
        }

        $this->entityManager->remove($userSkill);
        $this->entityManager->flush();
    }
}

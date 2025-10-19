<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\UserSkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
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
            throw new \LogicException('User not authenticated');
        }

        // Debug
        error_log("DELETE /me/skills - User ID: " . $user->getId());
        error_log("DELETE /me/skills - URI Variables: " . json_encode($uriVariables));
        error_log("DELETE /me/skills - Trying to find UserSkill ID: " . ($uriVariables['id'] ?? 'NO ID'));

        $userSkill = $this->userSkillRepository->find($uriVariables['id']);

        error_log("DELETE /me/skills - UserSkill found: " . ($userSkill ? 'YES' : 'NO'));
        if ($userSkill) {
            error_log("DELETE /me/skills - UserSkill owner ID: " . $userSkill->getOwner()->getId());
        }

        if (!$userSkill || $userSkill->getOwner()->getId() !== $user->getId()) {
            throw new NotFoundHttpException('UserSkill not found or you do not have permission to delete it');
        }

        $this->entityManager->remove($userSkill);
        $this->entityManager->flush();
    }
}

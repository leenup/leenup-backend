<?php

namespace App\State\Provider\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Profile\MySkills;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Repository\UserSkillRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider pour récupérer les compétences de l'utilisateur connecté
 *
 * @implements ProviderInterface<MySkills>
 */
final class MySkillsProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private UserSkillRepository $userSkillRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // Si on demande une skill spécifique (GET /me/skills/{id})
        if (isset($uriVariables['id'])) {
            $userSkill = $this->userSkillRepository->find($uriVariables['id']);

            if (!$userSkill || $userSkill->getOwner()->getId() !== $user->getId()) {
                throw new NotFoundHttpException('UserSkill not found');
            }

            return $this->mapToDto($userSkill);
        }

        // Sinon, on retourne toutes les skills de l'user (GET /me/skills)
        $userSkills = $this->userSkillRepository->findBy(['owner' => $user]);

        return array_map(fn(UserSkill $us) => $this->mapToDto($us), $userSkills);
    }

    private function mapToDto(UserSkill $userSkill): MySkills
    {
        $dto = new MySkills();
        $dto->id = $userSkill->getId();
        $dto->skill = $userSkill->getSkill();
        $dto->type = $userSkill->getType();
        $dto->level = $userSkill->getLevel();
        $dto->createdAt = $userSkill->getCreatedAt();

        return $dto;
    }
}

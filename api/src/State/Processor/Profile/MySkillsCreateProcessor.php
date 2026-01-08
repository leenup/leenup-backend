<?php

namespace App\State\Processor\Profile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Profile\MySkills;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Repository\UserSkillRepository;
use App\Service\CardUnlocker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Processor pour créer une compétence pour l'utilisateur connecté
 *
 * @implements ProcessorInterface<MySkills, MySkills>
 */
final class MySkillsCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
        private UserSkillRepository $userSkillRepository,
        private CardUnlocker $cardUnlocker,
    ) {
    }

    /**
     * @param MySkills $data
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MySkills
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        // Vérifier si la combinaison (user, skill, type) existe déjà
        $existing = $this->userSkillRepository->findOneBy([
            'owner' => $user,
            'skill' => $data->skill,
            'type' => $data->type
        ]);

        if ($existing) {
            $violations = new ConstraintViolationList([
                new ConstraintViolation(
                    'You already have this skill with this type',
                    null,
                    [],
                    $data,
                    'skill',
                    $data->skill
                )
            ]);
            throw new ValidationException($violations);
        }

        // Créer le UserSkill
        $userSkill = new UserSkill();
        $userSkill->setOwner($user);
        $userSkill->setSkill($data->skill);
        $userSkill->setType($data->type);
        $userSkill->setLevel($data->level);
        $user->addUserSkill($userSkill);

        $this->entityManager->persist($userSkill);
        $this->cardUnlocker->unlockForUser($user, 'skill_added');
        $this->entityManager->flush();

        // Retourner le DTO mis à jour
        $data->id = $userSkill->getId();
        $data->createdAt = $userSkill->getCreatedAt();

        return $data;
    }
}

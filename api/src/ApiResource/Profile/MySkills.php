<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Entity\Skill;
use App\Entity\UserSkill;
use App\State\Processor\Profile\MySkillsCreateProcessor;
use App\State\Processor\Profile\MySkillsRemoveProcessor;
use App\State\Provider\Profile\MySkillsProvider;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ressource API pour gérer les compétences de l'utilisateur connecté
 */
#[ApiResource(
    shortName: 'MySkill',
    operations: [
        new GetCollection(
            uriTemplate: '/me/skills',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MySkillsProvider::class,
        ),
        new Post(
            uriTemplate: '/me/skills',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            processor: MySkillsCreateProcessor::class,
        ),
        new Get(
            uriTemplate: '/me/skills/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MySkillsProvider::class,
        ),
        new Delete(
            uriTemplate: '/me/skills/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MySkillsProvider::class,
            processor: MySkillsRemoveProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['my_skill:read']],
    denormalizationContext: ['groups' => ['my_skill:write']],
)]
class MySkills
{
    #[Groups(['my_skill:read'])]
    public ?int $id = null;

    #[Assert\NotNull(message: 'The skill cannot be null')]
    #[Groups(['my_skill:read', 'my_skill:write'])]
    public ?Skill $skill = null;

    #[Assert\NotBlank(message: 'The type cannot be blank')]
    #[Assert\Choice(
        choices: [UserSkill::TYPE_TEACH, UserSkill::TYPE_LEARN],
        message: 'The type must be either "teach" or "learn"'
    )]
    #[Groups(['my_skill:read', 'my_skill:write'])]
    public ?string $type = null;

    #[Assert\Choice(
        choices: [
            UserSkill::LEVEL_BEGINNER,
            UserSkill::LEVEL_INTERMEDIATE,
            UserSkill::LEVEL_ADVANCED,
            UserSkill::LEVEL_EXPERT
        ],
        message: 'The level must be one of: beginner, intermediate, advanced, expert'
    )]
    #[Groups(['my_skill:read', 'my_skill:write'])]
    public ?string $level = null;

    #[Groups(['my_skill:read'])]
    public ?\DateTimeImmutable $createdAt = null;
}

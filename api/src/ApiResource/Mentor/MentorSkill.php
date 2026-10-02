<?php

namespace App\ApiResource\Mentor;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Compétence enseignée par un mentor, embarquée dans {@see Mentor}.
 */
final class MentorSkill
{
    #[Groups(['mentor:read'])]
    public ?int $skillId = null;

    #[Groups(['mentor:read'])]
    public ?string $title = null;

    #[Groups(['mentor:read'])]
    public ?int $categoryId = null;

    #[Groups(['mentor:read'])]
    public ?string $categoryTitle = null;

    #[Groups(['mentor:read'])]
    public ?string $level = null;
}

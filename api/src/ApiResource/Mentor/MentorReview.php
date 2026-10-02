<?php

namespace App\ApiResource\Mentor;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Review reçue par un mentor, embarquée dans le détail {@see Mentor}.
 * Seuls le prénom et l'avatar du reviewer sont exposés.
 */
final class MentorReview
{
    #[Groups(['mentor:item:read'])]
    public ?int $rating = null;

    #[Groups(['mentor:item:read'])]
    public ?string $comment = null;

    #[Groups(['mentor:item:read'])]
    public ?\DateTimeImmutable $createdAt = null;

    #[Groups(['mentor:item:read'])]
    public ?string $reviewerFirstName = null;

    #[Groups(['mentor:item:read'])]
    public ?string $reviewerAvatarUrl = null;
}

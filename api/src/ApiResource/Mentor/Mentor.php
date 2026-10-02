<?php

namespace App\ApiResource\Mentor;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\State\Provider\Mentor\MentorCollectionProvider;
use App\State\Provider\Mentor\MentorItemProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Profil public d'un mentor (découverte). N'expose volontairement aucune donnée privée
 * (email, solde de tokens, dernière connexion...).
 */
#[ApiResource(
    shortName: 'Mentor',
    operations: [
        new GetCollection(
            uriTemplate: '/mentors',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MentorCollectionProvider::class,
            openapi: new OpenApiOperation(
                summary: 'Search mentors',
                parameters: [
                    new OpenApiParameter('skill', 'query', 'Skill id taught by the mentor', schema: ['type' => 'integer']),
                    new OpenApiParameter('category', 'query', 'Category id of a skill taught by the mentor', schema: ['type' => 'integer']),
                    new OpenApiParameter('q', 'query', 'Text search on first name, last name and taught skill titles', schema: ['type' => 'string']),
                    new OpenApiParameter('minRating', 'query', 'Minimum average rating (0 to 5)', schema: ['type' => 'number']),
                    new OpenApiParameter('order[averageRating]', 'query', 'Sort by average rating', schema: ['type' => 'string', 'enum' => ['asc', 'desc']]),
                    new OpenApiParameter('order[reviewCount]', 'query', 'Sort by number of reviews', schema: ['type' => 'string', 'enum' => ['asc', 'desc']]),
                    new OpenApiParameter('page', 'query', 'Page number', schema: ['type' => 'integer']),
                    new OpenApiParameter('itemsPerPage', 'query', 'Items per page', schema: ['type' => 'integer']),
                ],
            ),
        ),
        new Get(
            uriTemplate: '/mentors/{id}',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MentorItemProvider::class,
            normalizationContext: ['groups' => ['mentor:read', 'mentor:item:read']],
        ),
    ],
    normalizationContext: ['groups' => ['mentor:read']],
)]
class Mentor
{
    #[ApiProperty(identifier: true)]
    #[Groups(['mentor:read'])]
    public ?int $id = null;

    #[Groups(['mentor:read'])]
    public ?string $firstName = null;

    #[Groups(['mentor:read'])]
    public ?string $lastName = null;

    #[Groups(['mentor:read'])]
    public ?string $avatarUrl = null;

    #[Groups(['mentor:read'])]
    public ?string $bio = null;

    #[Groups(['mentor:read'])]
    public ?string $location = null;

    /** @var string[]|null */
    #[Groups(['mentor:read'])]
    public ?array $languages = null;

    #[Groups(['mentor:read'])]
    public ?string $exchangeFormat = null;

    #[Groups(['mentor:read'])]
    public ?float $averageRating = null;

    #[Groups(['mentor:read'])]
    public int $reviewCount = 0;

    /** @var MentorSkill[] */
    #[Groups(['mentor:read'])]
    public array $skills = [];

    /** @var MentorReview[] */
    #[Groups(['mentor:item:read'])]
    public array $recentReviews = [];
}

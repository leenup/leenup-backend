<?php

namespace App\ApiResource\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use App\Entity\TokenTransaction;
use App\State\Provider\Profile\MyTokenTransactionsProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Historique des mouvements de tokens de l'utilisateur connecté
 */
#[ApiResource(
    shortName: 'MyTokenTransaction',
    operations: [
        new GetCollection(
            uriTemplate: '/me/token-transactions',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: MyTokenTransactionsProvider::class,
            openapi: new OpenApiOperation(
                summary: 'Token history of the current user (most recent first)',
                parameters: [
                    new OpenApiParameter('type', 'query', 'Filter by transaction type', schema: ['type' => 'string', 'enum' => TokenTransaction::TYPES]),
                    new OpenApiParameter('page', 'query', 'Page number', schema: ['type' => 'integer']),
                    new OpenApiParameter('itemsPerPage', 'query', 'Items per page', schema: ['type' => 'integer']),
                ],
            ),
        ),
    ],
    normalizationContext: ['groups' => ['my_token_transaction:read']],
)]
class MyTokenTransactions
{
    #[ApiProperty(identifier: true)]
    #[Groups(['my_token_transaction:read'])]
    public ?int $id = null;

    /** Positif pour un crédit, négatif pour un débit. */
    #[Groups(['my_token_transaction:read'])]
    public int $amount = 0;

    #[Groups(['my_token_transaction:read'])]
    public ?string $type = null;

    #[Groups(['my_token_transaction:read'])]
    public int $balanceAfter = 0;

    /** IRI de la session liée (null si aucune ou si elle a été supprimée). */
    #[Groups(['my_token_transaction:read'])]
    public ?string $session = null;

    #[Groups(['my_token_transaction:read'])]
    public ?string $skillTitle = null;

    #[Groups(['my_token_transaction:read'])]
    public ?\DateTimeImmutable $createdAt = null;
}

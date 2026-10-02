<?php

namespace App\Entity;

use App\Repository\TokenTransactionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mouvement de tokens d'un utilisateur (relevé). Créé uniquement via {@see \App\Service\TokenLedger},
 * jamais modifié ensuite. Exposé via /me/token-transactions.
 */
#[ORM\Entity(repositoryClass: TokenTransactionRepository::class)]
#[ORM\Index(name: 'idx_token_transaction_user_created', columns: ['user_id', 'created_at'])]
class TokenTransaction
{
    // Types de mouvements
    public const TYPE_SIGNUP_BONUS = 'signup_bonus';
    public const TYPE_INITIAL_BALANCE = 'initial_balance';
    public const TYPE_SESSION_BOOKED = 'session_booked';
    public const TYPE_SESSION_GIVEN = 'session_given';
    public const TYPE_SESSION_REFUND = 'session_refund';

    public const TYPES = [
        self::TYPE_SIGNUP_BONUS,
        self::TYPE_INITIAL_BALANCE,
        self::TYPE_SESSION_BOOKED,
        self::TYPE_SESSION_GIVEN,
        self::TYPE_SESSION_REFUND,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Positif pour un crédit, négatif pour un débit. */
    #[ORM\Column]
    private int $amount;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Session $session;

    /** Solde de l'utilisateur juste après ce mouvement. */
    #[ORM\Column]
    private int $balanceAfter;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, int $amount, string $type, int $balanceAfter, ?Session $session = null)
    {
        if (!\in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown token transaction type "%s".', $type));
        }

        $this->user = $user;
        $this->amount = $amount;
        $this->type = $type;
        $this->balanceAfter = $balanceAfter;
        $this->session = $session;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function getBalanceAfter(): int
    {
        return $this->balanceAfter;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

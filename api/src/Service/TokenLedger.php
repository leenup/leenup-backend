<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\TokenTransaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée unique pour faire bouger le solde de tokens : chaque crédit/débit
 * met à jour User::tokenBalance et enregistre un TokenTransaction. Ne flush pas.
 */
final class TokenLedger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function credit(User $user, int $amount, string $type, ?Session $session = null): TokenTransaction
    {
        $user->addTokenBalance($amount);

        return $this->record($user, $amount, $type, $session);
    }

    public function debit(User $user, int $amount, string $type, ?Session $session = null): TokenTransaction
    {
        $user->removeTokenBalance($amount);

        return $this->record($user, -$amount, $type, $session);
    }

    /**
     * Trace le solde de départ d'un nouvel utilisateur (le solde lui-même est déjà posé par l'entité).
     */
    public function recordSignupBonus(User $user): ?TokenTransaction
    {
        if ($user->getTokenBalance() <= 0) {
            return null;
        }

        return $this->record($user, $user->getTokenBalance(), TokenTransaction::TYPE_SIGNUP_BONUS, null);
    }

    private function record(User $user, int $amount, string $type, ?Session $session): TokenTransaction
    {
        $transaction = new TokenTransaction($user, $amount, $type, $user->getTokenBalance(), $session);
        $this->entityManager->persist($transaction);

        return $transaction;
    }
}

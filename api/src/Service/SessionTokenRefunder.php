<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\TokenTransaction;

/**
 * Rend son token à l'étudiant quand une session payée est annulée ou supprimée.
 * Idempotent : le token n'est rendu qu'une fois.
 */
final class SessionTokenRefunder
{
    public function __construct(
        private TokenLedger $tokenLedger,
    ) {
    }

    public function refund(Session $session): void
    {
        if (!$session->isTokenSpent()) {
            return;
        }

        $student = $session->getStudent();
        if ($student !== null) {
            $this->tokenLedger->credit($student, 1, TokenTransaction::TYPE_SESSION_REFUND, $session);
        }
        $session->setTokenSpent(false);
    }
}

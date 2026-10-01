<?php

namespace App\Service;

use App\Entity\Session;

/**
 * Rend son token à l'étudiant quand une session payée est annulée ou supprimée.
 * Idempotent : le token n'est rendu qu'une fois.
 */
final class SessionTokenRefunder
{
    public function refund(Session $session): void
    {
        if (!$session->isTokenSpent()) {
            return;
        }

        $session->getStudent()?->addTokenBalance(1);
        $session->setTokenSpent(false);
    }
}

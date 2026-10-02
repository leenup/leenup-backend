<?php

namespace App\Service;

use App\Entity\Session;
use App\Entity\TokenTransaction;

/**
 * Effets de bord d'une session passée en "completed" :
 * crédit du mentor et déblocage des cartes des deux participants.
 * Doit être appelé par toute route qui fait passer une session en "completed".
 */
final class SessionCompletionHandler
{
    public function __construct(
        private CardUnlocker $cardUnlocker,
        private TokenLedger $tokenLedger,
    ) {
    }

    public function handle(Session $session): void
    {
        $mentor = $session->getMentor();
        if ($mentor !== null) {
            $this->tokenLedger->credit($mentor, 1, TokenTransaction::TYPE_SESSION_GIVEN, $session);
        }
        $this->cardUnlocker->unlockForUser($session->getMentor(), 'session_completed', [
            'sessionId' => $session->getId(),
            'role' => 'mentor',
        ]);
        $this->cardUnlocker->unlockForUser($session->getStudent(), 'session_completed', [
            'sessionId' => $session->getId(),
            'role' => 'student',
        ]);
    }
}

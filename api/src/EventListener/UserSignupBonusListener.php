<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\TokenLedger;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Trace le token offert à l'inscription dans l'historique, quel que soit le chemin de création du compte.
 */
#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: User::class)]
final class UserSignupBonusListener
{
    public function __construct(
        private TokenLedger $tokenLedger,
    ) {
    }

    public function prePersist(User $user): void
    {
        $this->tokenLedger->recordSignupBonus($user);
    }
}

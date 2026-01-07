<?php

namespace App\Service\CardCondition;

use App\Entity\Card;
use App\Entity\User;

class CardConditionEvaluator
{
    /**
     * @param iterable<ConditionCheckerInterface> $checkers
     */
    public function __construct(
        private iterable $checkers,
    ) {
    }

    /**
     * Vérifie si un utilisateur remplit les conditions d'une carte donnée.
     */
    public function userMeetsConditions(User $user, Card $card): bool
    {
        $conditions = $card->getConditions();

        if (empty($conditions)) {
            // Par sécurité, on considère qu'une carte sans conditions
            // n'est pas automatiquement attribuable.
            return false;
        }

        // Cas "all": toutes les conditions doivent être vraies
        if (isset($conditions['all']) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $condition) {
                if (!$this->evaluateSingleCondition($user, $condition)) {
                    return false;
                }
            }

            return true;
        }

        // Cas simple: une seule condition à la racine
        if (isset($conditions['type'])) {
            return $this->evaluateSingleCondition($user, $conditions);
        }

        // Structure non reconnue
        return false;
    }

    private function evaluateSingleCondition(User $user, array $condition): bool
    {
        $type = $condition['type'] ?? null;

        if ($type === null) {
            return false;
        }

        $checker = $this->findChecker($type);

        if ($checker === null) {
            return false;
        }

        return $checker->check($user, $condition);
    }

    private function findChecker(string $type): ?ConditionCheckerInterface
    {
        foreach ($this->checkers as $checker) {
            if ($checker->supports($type)) {
                return $checker;
            }
        }

        return null;
    }
}

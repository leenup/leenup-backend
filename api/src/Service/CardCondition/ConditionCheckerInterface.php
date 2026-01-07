<?php

namespace App\Service\CardCondition;

use App\Entity\User;

interface ConditionCheckerInterface
{
    /**
     * Indique si ce checker sait gérer un type de condition donné.
     */
    public function supports(string $type): bool;

    /**
     * Vérifie si l'utilisateur satisfait la condition donnée.
     *
     * Exemple de $params selon le type :
     *  - ['operator' => '>=', 'value' => 10]
     *  - ['value' => true]
     */
    public function check(User $user, array $params): bool;
}

<?php

namespace App\Service\Card;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\UserCard;
use App\Repository\CardRepository;
use App\Repository\UserCardRepository;
use App\Service\CardCondition\CardConditionEvaluator;
use Doctrine\ORM\EntityManagerInterface;

class CardUnlockerService
{
    public function __construct(
        private CardRepository $cardRepository,
        private UserCardRepository $userCardRepository,
        private CardConditionEvaluator $conditionEvaluator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vérifie toutes les cartes actives et attribue celles que l'utilisateur mérite
     * en fonction d'un ou plusieurs "types d'événements".
     *
     * Exemple d'appel :
     *  - $eventTypes = ['sessions_given']
     *  - $eventTypes = ['reviews_received']
     */
    public function unlockForUser(User $user, array $eventTypes = []): array
    {
        if ($eventTypes === []) {
            // Par sécurité, si aucun type d'événement n'est fourni,
            // on considère qu'il n'y a rien à faire.
            return [];
        }

        $unlocked = [];

        /** @var Card[] $cards */
        $cards = $this->cardRepository->findBy(['isActive' => true]);

        foreach ($cards as $card) {
            if (!$this->cardIsRelevantForEventTypes($card, $eventTypes)) {
                continue;
            }

            if (!$this->conditionEvaluator->userMeetsConditions($user, $card)) {
                continue;
            }

            // On vérifie que l'utilisateur ne possède pas déjà cette carte
            $existing = $this->userCardRepository->findOneBy([
                'user' => $user,
                'card' => $card,
            ]);

            if ($existing !== null) {
                continue;
            }

            $userCard = new UserCard();
            $userCard
                ->setUser($user)
                ->setCard($card)
                ->setObtainedAt(new \DateTimeImmutable())
                ->setSource('auto')
                ->setMeta([
                    'eventTypes' => $eventTypes,
                ])
            ;

            $this->entityManager->persist($userCard);
            $unlocked[] = $userCard;
        }

        if ($unlocked !== []) {
            $this->entityManager->flush();
        }

        return $unlocked;
    }

    /**
     * Vérifie rapidement si la carte contient au moins un type de condition
     * qui correspond aux eventTypes fournis.
     */
    private function cardIsRelevantForEventTypes(Card $card, array $eventTypes): bool
    {
        $conditions = $card->getConditions() ?? [];

        $typesInCard = [];

        if (isset($conditions['all']) && is_array($conditions['all'])) {
            foreach ($conditions['all'] as $cond) {
                if (isset($cond['type'])) {
                    $typesInCard[] = $cond['type'];
                }
            }
        } elseif (isset($conditions['type'])) {
            $typesInCard[] = $conditions['type'];
        }

        return array_intersect($typesInCard, $eventTypes) !== [];
    }
}

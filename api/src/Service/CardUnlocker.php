<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserCard;
use App\Repository\CardRepository;
use App\Repository\UserCardRepository;
use Doctrine\ORM\EntityManagerInterface;

final class CardUnlocker
{
    public function __construct(
        private CardRepository $cardRepository,
        private UserCardRepository $userCardRepository,
        private CardConditionEvaluator $conditionEvaluator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return UserCard[]
     */
    public function unlockForUser(User $user, ?string $source = null, ?array $meta = null): array
    {
        $unlocked = [];
        $cards = $this->cardRepository->findBy(['isActive' => true]);

        foreach ($cards as $card) {
            if (!$this->conditionEvaluator->matches($user, $card)) {
                continue;
            }

            $existing = $this->userCardRepository->findOneBy([
                'user' => $user,
                'card' => $card,
            ]);

            if ($existing !== null) {
                continue;
            }

            $userCard = new UserCard()
                ->setUser($user)
                ->setCard($card);

            if ($source !== null) {
                $userCard->setSource($source);
            }

            if ($meta !== null) {
                $userCard->setMeta($meta);
            }

            $this->entityManager->persist($userCard);
            $unlocked[] = $userCard;
        }

        return $unlocked;
    }
}

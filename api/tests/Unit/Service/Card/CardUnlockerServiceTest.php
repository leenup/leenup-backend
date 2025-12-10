<?php

namespace App\Tests\Unit\Service\Card;

use App\Entity\Card;
use App\Entity\User;
use App\Entity\UserCard;
use App\Repository\CardRepository;
use App\Repository\UserCardRepository;
use App\Service\Card\CardUnlockerService;
use App\Service\CardCondition\CardConditionEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CardUnlockerServiceTest extends TestCase
{
    public function testUnlockForUserCreatesUserCardWhenEligibleAndNotExisting(): void
    {
        $user = new User();

        $card1 = new Card();
        $card1->setConditions([
            'all' => [
                ['type' => 'sessions_given', 'operator' => '>=', 'value' => 10],
            ],
        ]);

        $card2 = new Card();
        $card2->setConditions([
            'all' => [
                ['type' => 'reviews_received', 'operator' => '>=', 'value' => 3],
            ],
        ]);

        $cardRepository = $this->createMock(CardRepository::class);
        $cardRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['isActive' => true])
            ->willReturn([$card1, $card2]);

        $userCardRepository = $this->createMock(UserCardRepository::class);
        $userCardRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'card' => $card1,
            ])
            ->willReturn(null);

        $evaluator = $this->createMock(CardConditionEvaluator::class);
        // card1 est pertinente pour "sessions_given" et sera éligible
        $evaluator
            ->expects($this->once())
            ->method('userMeetsConditions')
            ->with($user, $card1)
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(UserCard::class));

        $entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new CardUnlockerService(
            $cardRepository,
            $userCardRepository,
            $evaluator,
            $entityManager
        );

        $unlocked = $service->unlockForUser($user, ['sessions_given']);

        $this->assertCount(1, $unlocked);
        $this->assertInstanceOf(UserCard::class, $unlocked[0]);
        $this->assertSame($user, $unlocked[0]->getUser());
        $this->assertSame($card1, $unlocked[0]->getCard());
    }

    public function testUnlockForUserDoesNothingWhenEventTypesEmpty(): void
    {
        $user = new User();

        $cardRepository = $this->createMock(CardRepository::class);
        $cardRepository
            ->expects($this->never())
            ->method('findBy');

        $userCardRepository = $this->createMock(UserCardRepository::class);
        $evaluator = $this->createMock(CardConditionEvaluator::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new CardUnlockerService(
            $cardRepository,
            $userCardRepository,
            $evaluator,
            $entityManager
        );

        $unlocked = $service->unlockForUser($user, []);

        $this->assertSame([], $unlocked);
    }

    public function testUnlockForUserDoesNotCreateDuplicateWhenUserAlreadyHasCard(): void
    {
        $user = new User();

        $card = new Card();
        $card->setConditions([
            'all' => [
                ['type' => 'sessions_given', 'operator' => '>=', 'value' => 10],
            ],
        ]);

        $existingUserCard = new UserCard();
        $existingUserCard->setUser($user)->setCard($card);

        $cardRepository = $this->createMock(CardRepository::class);
        $cardRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['isActive' => true])
            ->willReturn([$card]);

        $userCardRepository = $this->createMock(UserCardRepository::class);
        $userCardRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'card' => $card,
            ])
            ->willReturn($existingUserCard);

        $evaluator = $this->createMock(CardConditionEvaluator::class);
        $evaluator
            ->expects($this->once())
            ->method('userMeetsConditions')
            ->with($user, $card)
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects($this->never())
            ->method('persist');

        $entityManager
            ->expects($this->never())
            ->method('flush');

        $service = new CardUnlockerService(
            $cardRepository,
            $userCardRepository,
            $evaluator,
            $entityManager
        );

        $unlocked = $service->unlockForUser($user, ['sessions_given']);

        $this->assertSame([], $unlocked);
    }
}

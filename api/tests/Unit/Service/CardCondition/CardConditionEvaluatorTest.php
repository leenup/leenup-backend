<?php

namespace App\Tests\Unit\Service\CardCondition;

use App\Entity\Card;
use App\Entity\User;
use App\Service\CardCondition\CardConditionEvaluator;
use App\Service\CardCondition\ConditionCheckerInterface;
use PHPUnit\Framework\TestCase;

class CardConditionEvaluatorTest extends TestCase
{
    public function testUserMeetsAllConditionsWhenAllCheckersReturnTrue(): void
    {
        $user = new User();
        $card = new Card();
        $card->setConditions([
            'all' => [
                [
                    'type' => 'sessions_given',
                    'operator' => '>=',
                    'value' => 10,
                ],
                [
                    'type' => 'reviews_received',
                    'operator' => '>=',
                    'value' => 3,
                ],
            ],
        ]);

        $sessionsChecker = $this->createMock(ConditionCheckerInterface::class);
        $sessionsChecker
            ->method('supports')
            ->willReturnCallback(static fn (string $type): bool => $type === 'sessions_given');

        $sessionsChecker
            ->method('check')
            ->willReturn(true);

        $reviewsChecker = $this->createMock(ConditionCheckerInterface::class);
        $reviewsChecker
            ->method('supports')
            ->willReturnCallback(static fn (string $type): bool => $type === 'reviews_received');

        $reviewsChecker
            ->method('check')
            ->willReturn(true);

        $evaluator = new CardConditionEvaluator([$sessionsChecker, $reviewsChecker]);

        $this->assertTrue($evaluator->userMeetsConditions($user, $card));
    }

    public function testUserDoesNotMeetConditionsWhenOneCheckerFails(): void
    {
        $user = new User();
        $card = new Card();
        $card->setConditions([
            'all' => [
                [
                    'type' => 'sessions_given',
                    'operator' => '>=',
                    'value' => 10,
                ],
                [
                    'type' => 'reviews_received',
                    'operator' => '>=',
                    'value' => 3,
                ],
            ],
        ]);

        $sessionsChecker = $this->createMock(ConditionCheckerInterface::class);
        $sessionsChecker
            ->method('supports')
            ->willReturnCallback(static fn (string $type): bool => $type === 'sessions_given');
        $sessionsChecker
            ->method('check')
            ->willReturn(true);

        $reviewsChecker = $this->createMock(ConditionCheckerInterface::class);
        $reviewsChecker
            ->method('supports')
            ->willReturnCallback(static fn (string $type): bool => $type === 'reviews_received');
        $reviewsChecker
            ->method('check')
            ->willReturn(false);

        $evaluator = new CardConditionEvaluator([$sessionsChecker, $reviewsChecker]);

        $this->assertFalse($evaluator->userMeetsConditions($user, $card));
    }

    public function testUserMeetsSingleRootCondition(): void
    {
        $user = new User();
        $card = new Card();
        $card->setConditions([
            'type' => 'sessions_given',
            'operator' => '>=',
            'value' => 5,
        ]);

        $sessionsChecker = $this->createMock(ConditionCheckerInterface::class);
        $sessionsChecker
            ->method('supports')
            ->willReturn(true);

        $sessionsChecker
            ->method('check')
            ->with($user, $this->arrayHasKey('value'))
            ->willReturn(true);

        $evaluator = new CardConditionEvaluator([$sessionsChecker]);

        $this->assertTrue($evaluator->userMeetsConditions($user, $card));
    }

    public function testUserDoesNotMeetConditionsWithUnknownType(): void
    {
        $user = new User();
        $card = new Card();
        $card->setConditions([
            'type' => 'unknown_type',
        ]);

        $checker = $this->createMock(ConditionCheckerInterface::class);
        $checker
            ->method('supports')
            ->willReturn(false);

        $evaluator = new CardConditionEvaluator([$checker]);

        $this->assertFalse($evaluator->userMeetsConditions($user, $card));
    }
}

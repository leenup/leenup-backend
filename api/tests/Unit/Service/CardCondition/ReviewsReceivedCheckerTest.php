<?php

namespace App\Tests\Unit\Service\CardCondition;

use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Service\CardCondition\ReviewsReceivedChecker;
use PHPUnit\Framework\TestCase;

class ReviewsReceivedCheckerTest extends TestCase
{
    public function testSupportsOnlyReviewsReceived(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $checker = new ReviewsReceivedChecker($repo);

        $this->assertTrue($checker->supports('reviews_received'));
        $this->assertFalse($checker->supports('sessions_given'));
        $this->assertFalse($checker->supports('other_type'));
    }

    public function testCheckReturnsTrueWhenCountMeetsCondition(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $user = new User();

        $repo
            ->expects($this->once())
            ->method('count')
            ->with([
                'reviewed' => $user,
            ])
            ->willReturn(3);

        $checker = new ReviewsReceivedChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
            'value' => 3,
        ]);

        $this->assertTrue($result);
    }

    public function testCheckReturnsFalseWhenCountBelowCondition(): void
    {
        $repo = $this->createMock(ReviewRepository::class);
        $user = new User();

        $repo
            ->expects($this->once())
            ->method('count')
            ->with([
                'reviewed' => $user,
            ])
            ->willReturn(1);

        $checker = new ReviewsReceivedChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
            'value' => 3,
        ]);

        $this->assertFalse($result);
    }
}

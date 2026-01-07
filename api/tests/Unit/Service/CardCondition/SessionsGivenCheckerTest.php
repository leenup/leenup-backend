<?php

namespace App\Tests\Unit\Service\CardCondition;

use App\Entity\User;
use App\Repository\SessionRepository;
use App\Service\CardCondition\SessionsGivenChecker;
use PHPUnit\Framework\TestCase;

class SessionsGivenCheckerTest extends TestCase
{
    public function testSupportsOnlySessionsGiven(): void
    {
        $repo = $this->createMock(SessionRepository::class);
        $checker = new SessionsGivenChecker($repo);

        $this->assertTrue($checker->supports('sessions_given'));
        $this->assertFalse($checker->supports('sessions_taken'));
        $this->assertFalse($checker->supports('other_type'));
    }

    public function testCheckReturnsTrueWhenCountMeetsCondition(): void
    {
        $repo = $this->createMock(SessionRepository::class);
        $user = new User();

        $repo
            ->expects($this->once())
            ->method('count')
            ->with([
                'mentor' => $user,
                'status' => 'completed',
            ])
            ->willReturn(10);

        $checker = new SessionsGivenChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
            'value' => 5,
        ]);

        $this->assertTrue($result);
    }

    public function testCheckReturnsFalseWhenCountBelowCondition(): void
    {
        $repo = $this->createMock(SessionRepository::class);
        $user = new User();

        $repo
            ->expects($this->once())
            ->method('count')
            ->with([
                'mentor' => $user,
                'status' => 'completed',
            ])
            ->willReturn(3);

        $checker = new SessionsGivenChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
            'value' => 5,
        ]);

        $this->assertFalse($result);
    }

    public function testCheckReturnsFalseWhenValueIsMissing(): void
    {
        $repo = $this->createMock(SessionRepository::class);
        $user = new User();

        $repo
            ->expects($this->once())
            ->method('count')
            ->willReturn(10);

        $checker = new SessionsGivenChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
        ]);

        $this->assertFalse($result);
    }
}

<?php

namespace App\Tests\Unit\Service\CardCondition;

use App\Entity\User;
use App\Repository\SessionRepository;
use App\Service\CardCondition\SessionsTakenChecker;
use PHPUnit\Framework\TestCase;

class SessionsTakenCheckerTest extends TestCase
{
    public function testSupportsOnlySessionsTaken(): void
    {
        $repo = $this->createMock(SessionRepository::class);
        $checker = new SessionsTakenChecker($repo);

        $this->assertTrue($checker->supports('sessions_taken'));
        $this->assertFalse($checker->supports('sessions_given'));
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
                'student' => $user,
                'status' => 'completed',
            ])
            ->willReturn(5);

        $checker = new SessionsTakenChecker($repo);

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
                'student' => $user,
                'status' => 'completed',
            ])
            ->willReturn(2);

        $checker = new SessionsTakenChecker($repo);

        $result = $checker->check($user, [
            'operator' => '>=',
            'value' => 5,
        ]);

        $this->assertFalse($result);
    }
}

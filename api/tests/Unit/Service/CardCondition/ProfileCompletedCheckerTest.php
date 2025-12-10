<?php

namespace App\Tests\Unit\Service\CardCondition;

use App\Entity\User;
use App\Entity\UserSkill;
use App\Service\CardCondition\ProfileCompletedChecker;
use PHPUnit\Framework\TestCase;

class ProfileCompletedCheckerTest extends TestCase
{
    public function testSupportsProfileTypes(): void
    {
        $checker = new ProfileCompletedChecker();

        $this->assertTrue($checker->supports('has_avatar'));
        $this->assertTrue($checker->supports('has_bio'));
        $this->assertTrue($checker->supports('skills_count'));

        $this->assertFalse($checker->supports('sessions_given'));
    }

    public function testHasAvatarTrueWhenAvatarSet(): void
    {
        $user = new User();
        $user->setAvatarUrl('https://example.com/avatar.png');

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'has_avatar',
            'value' => true,
        ]);

        $this->assertTrue($result);
    }

    public function testHasAvatarFalseWhenNoAvatar(): void
    {
        $user = new User();
        $user->setAvatarUrl(null);

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'has_avatar',
            'value' => true,
        ]);

        $this->assertFalse($result);
    }

    public function testHasBioTrueWhenBioSet(): void
    {
        $user = new User();
        $user->setBio('Developer and mentor');

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'has_bio',
            'value' => true,
        ]);

        $this->assertTrue($result);
    }

    public function testHasBioFalseWhenEmpty(): void
    {
        $user = new User();
        $user->setBio('');

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'has_bio',
            'value' => true,
        ]);

        $this->assertFalse($result);
    }

    public function testSkillsCountMeetsCondition(): void
    {
        $user = new User();
        $user->addUserSkill(new UserSkill());
        $user->addUserSkill(new UserSkill());
        $user->addUserSkill(new UserSkill());

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'skills_count',
            'operator' => '>=',
            'value' => 3,
        ]);

        $this->assertTrue($result);
    }

    public function testSkillsCountBelowCondition(): void
    {
        $user = new User();
        $user->addUserSkill(new UserSkill());

        $checker = new ProfileCompletedChecker();

        $result = $checker->check($user, [
            'type' => 'skills_count',
            'operator' => '>=',
            'value' => 3,
        ]);

        $this->assertFalse($result);
    }
}

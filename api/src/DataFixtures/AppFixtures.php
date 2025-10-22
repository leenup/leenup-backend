<?php

namespace App\DataFixtures;

use App\Story\DefaultReviewsStory;
use App\Story\DefaultSessionsStory;
use App\Story\DefaultUserSkillsStory;
use App\Story\DefaultUsersStory;
use App\Story\DefaultCategoriesStory;
use App\Story\DefaultSkillsStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {

        echo "Loading DefaultCategoriesStory...\n";
        DefaultCategoriesStory::load();

        echo "Loading DefaultSkillsStory...\n";
        DefaultSkillsStory::load();

        echo "Loading DefaultUsersStory...\n";
        DefaultUsersStory::load();

        echo "Loading DefaultUserSkillsStory...\n";
        DefaultUserSkillsStory::load();

        echo "Loading DefaultSessionsStory...\n";
        DefaultSessionsStory::load();

        echo "Loading DefaultReviewsStory...\n";
        DefaultReviewsStory::load();

        echo "Done!\n";
    }
}

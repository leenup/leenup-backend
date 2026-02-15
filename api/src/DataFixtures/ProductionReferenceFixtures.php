<?php

namespace App\DataFixtures;

use App\Story\DefaultCardsStory;
use App\Story\DefaultCategoriesStory;
use App\Story\DefaultSkillsStory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class ProductionReferenceFixtures extends Fixture implements FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        echo "Loading production reference fixtures...\n";

        echo "Loading DefaultCategoriesStory...\n";
        DefaultCategoriesStory::load();

        echo "Loading DefaultSkillsStory...\n";
        DefaultSkillsStory::load();

        echo "Loading DefaultCardsStory...\n";
        DefaultCardsStory::load();

        echo "Done!\n";
    }

    public static function getGroups(): array
    {
        return ['prod', 'production'];
    }
}

<?php

namespace App\Story;

use App\Factory\CategoryFactory;
use Zenstruck\Foundry\Story;

final class DefaultCategoriesStory extends Story
{
    private const CATEGORIES = [
        'Création & Design',
        'Outils & Production',
        'Communication & SoftSkills',
    ];

    public function build(): void
    {
        foreach (self::CATEGORIES as $title) {
            CategoryFactory::createOne(['title' => $title]);
        }

        echo "✅ " . CategoryFactory::count() . " catégories créées\n";
    }
}

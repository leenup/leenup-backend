<?php

namespace App\Story;

use App\Factory\CategoryFactory;
use App\Factory\SkillFactory;
use Zenstruck\Foundry\Story;

final class DefaultSkillsStory extends Story
{
    private const SKILLS_BY_CATEGORY = [
        'Création & Design' => [
            'Graphisme',
            'UI/UX Design',
            'Illustration',
            'Branding',
            'Edition',
            'IA Créative',
        ],
        'Outils & Production' => [
            'Figma',
            'Adobe',
            'Animation',
            'Notion',
            'Production',
        ],
        'Communication & SoftSkills' => [
            'Prise de parole',
            'Storytelling',
            'Rédaction',
            'Organisation',
        ],
    ];

    public function build(): void
    {
        foreach (self::SKILLS_BY_CATEGORY as $categoryTitle => $skills) {
            $category = CategoryFactory::find(['title' => $categoryTitle]);

            if (!$category) {
                continue;
            }

            foreach ($skills as $skillTitle) {
                SkillFactory::createOne([
                    'title' => $skillTitle,
                    'category' => $category,
                ]);
            }
        }

        echo "✅ " . SkillFactory::count() . " skills créées\n";
    }
}

<?php

namespace App\Story;

use App\Factory\CategoryFactory;
use Zenstruck\Foundry\Story;

final class DefaultCategoriesStory extends Story
{
    public function build(): void
    {
        // Catégories principales pour LeenUp (cours web)
        CategoryFactory::createOne(['title' => 'Développement Web']);
        CategoryFactory::createOne(['title' => 'Développement Mobile']);
        CategoryFactory::createOne(['title' => 'Design & UX/UI']);
        CategoryFactory::createOne(['title' => 'Marketing Digital']);
        CategoryFactory::createOne(['title' => 'Graphisme']);
        CategoryFactory::createOne(['title' => 'No-Code / Low-Code']);
        CategoryFactory::createOne(['title' => 'Bases de données']);
        CategoryFactory::createOne(['title' => 'DevOps & Cloud']);
        CategoryFactory::createOne(['title' => 'Cybersécurité']);
        CategoryFactory::createOne(['title' => 'Intelligence Artificielle']);

        echo "10 catégories LeenUp créées\n";
    }
}

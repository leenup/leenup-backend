<?php

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class DefaultUsersStory extends Story
{
    public function build(): void
    {
        // Admin de la plateforme
        UserFactory::createOne([
            'email' => 'admin@leenup.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'plainPassword' => 'admin123',
        ]);

        echo "Admin créé: admin@leenup.com / admin123\n";

        // Quelques professeurs / formateurs réalistes
        UserFactory::createOne([
            'email' => 'sarah.dev@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
        ]);

        UserFactory::createOne([
            'email' => 'marc.design@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
        ]);

        UserFactory::createOne([
            'email' => 'julie.marketing@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
        ]);

        UserFactory::createOne([
            'email' => 'thomas.fullstack@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
        ]);

        UserFactory::createOne([
            'email' => 'lea.ux@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
        ]);

        // Utilisateur de test simple
        UserFactory::createOne([
            'email' => 'user@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'user123',
        ]);

        echo "User de test créé: user@leenup.com / user123\n";

        // 15 utilisateurs aléatoires (élèves/profs)
        UserFactory::createMany(15);

        echo "15 utilisateurs aléatoires créés\n";
        echo "Total: 22 utilisateurs\n";
    }
}

<?php

namespace App\Story;

use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class DefaultUsersStory extends Story
{
    public function build(): void
    {
        // Admin de la plateforme (pas mentor par défaut)
        UserFactory::createOne([
            'email' => 'admin@leenup.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'plainPassword' => 'admin123',
            'isMentor' => false,
        ]);

        echo "Admin créé: admin@leenup.com / admin123\n";

        UserFactory::createOne([
            'email' => 'admin2@leenup.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'plainPassword' => 'admin123',
            'isMentor' => false,
        ]);

        // Quelques professeurs / formateurs réalistes (mentors)
        UserFactory::createOne([
            'email' => 'sarah.dev@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        UserFactory::createOne([
            'email' => 'marc.design@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        UserFactory::createOne([
            'email' => 'julie.marketing@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        UserFactory::createOne([
            'email' => 'thomas.fullstack@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        UserFactory::createOne([
            'email' => 'lea.ux@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
        ]);

        // Utilisateur de test simple (plutôt côté élève)
        UserFactory::createOne([
            'email' => 'user@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'user123',
            'isMentor' => false,
        ]);

        echo "User de test créé: user@leenup.com / user123\n";

        // 15 utilisateurs aléatoires (élèves/profs) avec tous les nouveaux champs fournis par la factory
        UserFactory::createMany(15);

        echo "✅ " . UserFactory::count() . " users créées\n";
    }
}

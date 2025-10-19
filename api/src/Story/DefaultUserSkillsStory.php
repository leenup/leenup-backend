<?php

namespace App\Story;

use App\Entity\UserSkill;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use Zenstruck\Foundry\Story;

final class DefaultUserSkillsStory extends Story
{
    public function build(): void
    {
        echo "Ajout de compétences aux utilisateurs...\n";

        // Admin 1
        $this->addSkillsToUser('admin@leenup.com', [
            ['React', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Docker', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['PostgreSQL', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
        ]);

        // Sarah - Dev Web
        $this->addSkillsToUser('sarah.dev@leenup.com', [
            ['React', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['TypeScript', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Node.js', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Vue.js', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
        ]);

        // Marc - Design
        $this->addSkillsToUser('marc.design@leenup.com', [
            ['Figma', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['UI Design', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Photoshop', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
        ]);

        // Julie - Marketing
        $this->addSkillsToUser('julie.marketing@leenup.com', [
            ['SEO', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Content Marketing', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Google Analytics', UserSkill::TYPE_LEARN, UserSkill::LEVEL_INTERMEDIATE],
        ]);

        // Thomas - Fullstack
        $this->addSkillsToUser('thomas.fullstack@leenup.com', [
            ['PHP', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Symfony', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Docker', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Kubernetes', UserSkill::TYPE_LEARN, UserSkill::LEVEL_INTERMEDIATE],
        ]);

        // Lea - UX
        $this->addSkillsToUser('lea.ux@leenup.com', [
            ['UX Design', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Wireframing', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Figma', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
        ]);

        // User test - Apprenant
        $this->addSkillsToUser('user@leenup.com', [
            ['React', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
            ['JavaScript', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
        ]);

        // Pour les 15 utilisateurs aléatoires
        $this->addRandomSkillsToRandomUsers();

        echo "Compétences ajoutées avec succès !\n";
    }

    private function addSkillsToUser(string $email, array $skills): void
    {
        $user = UserFactory::find(['email' => $email]);

        foreach ($skills as [$skillTitle, $type, $level]) {
            $skill = SkillFactory::find(['title' => $skillTitle]);

            if ($skill) {
                UserSkillFactory::createOne([
                    'owner' => $user,
                    'skill' => $skill,
                    'type' => $type,
                    'level' => $level
                ]);
            }
        }
    }

    private function addRandomSkillsToRandomUsers(): void
    {
        $excludedEmails = [
            'admin@leenup.com', 'admin2@leenup.com',
            'sarah.dev@leenup.com', 'marc.design@leenup.com',
            'julie.marketing@leenup.com', 'thomas.fullstack@leenup.com',
            'lea.ux@leenup.com', 'user@leenup.com'
        ];

        $allUsers = UserFactory::findBy([]);
        $allSkills = SkillFactory::all();

        foreach ($allUsers as $user) {
            if (in_array($user->getEmail(), $excludedEmails)) {
                continue;
            }

            // Entre 1 et 5 compétences par utilisateur
            $nbSkills = rand(1, 5);
            $selectedSkills = [];

            for ($i = 0; $i < $nbSkills; $i++) {
                $randomSkill = $allSkills[array_rand($allSkills)];

                // Éviter les doublons
                $key = $randomSkill->getId();
                if (isset($selectedSkills[$key])) {
                    continue;
                }

                $selectedSkills[$key] = true;

                UserSkillFactory::createOne([
                    'owner' => $user,
                    'skill' => $randomSkill,
                    'type' => rand(0, 1) ? UserSkill::TYPE_TEACH : UserSkill::TYPE_LEARN,
                    'level' => [
                        UserSkill::LEVEL_BEGINNER,
                        UserSkill::LEVEL_INTERMEDIATE,
                        UserSkill::LEVEL_ADVANCED,
                        UserSkill::LEVEL_EXPERT
                    ][rand(0, 3)]
                ]);
            }
        }
    }
}

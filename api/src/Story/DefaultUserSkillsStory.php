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
            ['Organisation', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Production', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Notion', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
        ]);

        // Sarah - plutôt "prod / organisation"
        $this->addSkillsToUser('sarah.dev@leenup.com', [
            ['Production', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Notion', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Organisation', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['UI/UX Design', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
        ]);

        // Marc - Design
        $this->addSkillsToUser('marc.design@leenup.com', [
            ['Figma', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['UI/UX Design', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Adobe', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Branding', UserSkill::TYPE_LEARN, UserSkill::LEVEL_INTERMEDIATE],
        ]);

        // Julie - Communication
        $this->addSkillsToUser('julie.marketing@leenup.com', [
            ['Storytelling', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Rédaction', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Prise de parole', UserSkill::TYPE_LEARN, UserSkill::LEVEL_INTERMEDIATE],
        ]);

        // Thomas - "prod / org"
        $this->addSkillsToUser('thomas.fullstack@leenup.com', [
            ['Production', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Organisation', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Notion', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['IA Créative', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
        ]);

        // Lea - UX
        $this->addSkillsToUser('lea.ux@leenup.com', [
            ['UI/UX Design', UserSkill::TYPE_TEACH, UserSkill::LEVEL_EXPERT],
            ['Figma', UserSkill::TYPE_TEACH, UserSkill::LEVEL_ADVANCED],
            ['Illustration', UserSkill::TYPE_LEARN, UserSkill::LEVEL_INTERMEDIATE],
        ]);

        // User test - Apprenant
        $this->addSkillsToUser('user@leenup.com', [
            ['UI/UX Design', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
            ['Notion', UserSkill::TYPE_LEARN, UserSkill::LEVEL_BEGINNER],
        ]);

        // Pour les utilisateurs aléatoires
        $this->addRandomSkillsToRandomUsers();

        echo "✅ Compétences ajoutées avec succès !\n";
    }

    /**
     * @param array<int, array{0:string,1:string,2:string}> $skills
     */
    private function addSkillsToUser(string $email, array $skills): void
    {
        $user = UserFactory::find(['email' => $email]);

        if (!$user) {
            return;
        }

        foreach ($skills as [$skillTitle, $type, $level]) {
            $skill = SkillFactory::find(['title' => $skillTitle]);

            if ($skill) {
                UserSkillFactory::createOne([
                    'owner' => $user,
                    'skill' => $skill,
                    'type' => $type,
                    'level' => $level,
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
            'lea.ux@leenup.com', 'user@leenup.com',
        ];

        $allUsers = UserFactory::findBy([]);
        $allSkills = SkillFactory::all();

        foreach ($allUsers as $user) {
            if (in_array($user->getEmail(), $excludedEmails, true)) {
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
                        UserSkill::LEVEL_EXPERT,
                    ][rand(0, 3)],
                ]);
            }
        }
    }
}

<?php

namespace App\Story;

use App\Entity\Session;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class DefaultSessionsStory extends Story
{
    public function build(): void
    {
        echo "Création de sessions réalistes...\n";

        $sarah = UserFactory::find(['email' => 'sarah.dev@leenup.com']);
        $marc = UserFactory::find(['email' => 'marc.design@leenup.com']);
        $julie = UserFactory::find(['email' => 'julie.marketing@leenup.com']);
        $thomas = UserFactory::find(['email' => 'thomas.fullstack@leenup.com']);
        $lea = UserFactory::find(['email' => 'lea.ux@leenup.com']);
        $user = UserFactory::find(['email' => 'user@leenup.com']);

        // New reference skills (aligned with new categories/skills)
        $uiUxSkill = SkillFactory::find(['title' => 'UI/UX Design']);
        $figmaSkill = SkillFactory::find(['title' => 'Figma']);
        $storytellingSkill = SkillFactory::find(['title' => 'Storytelling']);
        $notionSkill = SkillFactory::find(['title' => 'Notion']);
        $brandingSkill = SkillFactory::find(['title' => 'Branding']);

        // Guard: avoid seeding invalid sessions if skills are missing
        if (!$uiUxSkill || !$figmaSkill || !$storytellingSkill || !$notionSkill || !$brandingSkill) {
            echo "⚠️  Skills manquantes. Assure-toi d'avoir exécuté DefaultSkillsStory avec les nouvelles valeurs.\n";
            return;
        }

        // 1) Session confirmée - UI/UX
        SessionFactory::createOne([
            'mentor' => $sarah,
            'student' => $user,
            'skill' => $uiUxSkill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => $this->nextMondayAt(17, 0),
            'duration' => 60,
            'location' => 'Zoom',
            'notes' => 'Intro UI/UX : parcours utilisateur, hiérarchie visuelle, bonnes pratiques',
        ]);

        // 2) Session en attente - Figma
        SessionFactory::createOne([
            'mentor' => $marc,
            'student' => $user,
            'skill' => $figmaSkill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => $this->nextMondayAt(19, 0),
            'duration' => 90,
            'location' => 'Google Meet',
            'notes' => 'Bases de Figma : frames, composants, auto-layout, prototypes',
        ]);

        // 3) Session complétée - Storytelling
        SessionFactory::createOne([
            'mentor' => $julie,
            'student' => $thomas,
            'skill' => $storytellingSkill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => $this->nextMondayAt(17, 0)->modify('-7 days'),
            'duration' => 60,
            'location' => 'Zoom',
            'notes' => 'Storytelling : structure narrative, message clé, angle et audience',
        ]);

        // 4) Session confirmée - Notion
        SessionFactory::createOne([
            'mentor' => $thomas,
            'student' => $sarah,
            'skill' => $notionSkill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => $this->nextMondayAt(19, 0)->modify('+7 days'),
            'duration' => 120,
            'location' => 'En personne - Paris',
            'notes' => 'Notion avancé : base de données, templates, workflows, organisation perso/pro',
        ]);

        // 5) Session annulée - Branding
        SessionFactory::createOne([
            'mentor' => $lea,
            'student' => $marc,
            'skill' => $brandingSkill,
            'status' => Session::STATUS_CANCELLED,
            'scheduledAt' => $this->nextMondayAt(17, 0)->modify('-14 days'),
            'duration' => 60,
            'location' => 'Discord',
            'notes' => 'Session annulée par le mentor',
        ]);

        // Random sessions (status + scheduledAt). Factories should fill remaining fields.
        SessionFactory::createMany(15, function () {
            $hour = self::faker()->randomElement([17, 19]);

            return [
                'status' => self::faker()->randomElement([
                    Session::STATUS_PENDING,
                    Session::STATUS_CONFIRMED,
                    Session::STATUS_COMPLETED,
                ]),
                'scheduledAt' => $this->nextMondayAt($hour, 0)->modify(sprintf('+%d days', self::faker()->numberBetween(0, 56))),
            ];
        });

        echo "✅ " . SessionFactory::count() . " sessions créées\n";
    }

    private static function faker()
    {
        return \Faker\Factory::create();
    }

    private function nextMondayAt(int $hour, int $minute): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('next monday'))
            ->setTime($hour, $minute);
    }
}

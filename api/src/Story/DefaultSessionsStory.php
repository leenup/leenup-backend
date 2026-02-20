<?php

namespace App\Story;

use App\Entity\Session;
use App\Entity\UserSkill;
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

        $reactSkill = SkillFactory::find(['title' => 'React']);
        $figmaSkill = SkillFactory::find(['title' => 'Figma']);
        $seoSkill = SkillFactory::find(['title' => 'SEO']);
        $phpSkill = SkillFactory::find(['title' => 'PHP']);
        $uxSkill = SkillFactory::find(['title' => 'UX Design']);

        SessionFactory::createOne([
            'mentor' => $sarah,
            'student' => $user,
            'skill' => $reactSkill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => $this->nextMondayAt(17, 0),
            'duration' => 60,
            'location' => 'Zoom',
            'notes' => 'Introduction à React - Composants et Props',
        ]);

        SessionFactory::createOne([
            'mentor' => $marc,
            'student' => $user,
            'skill' => $figmaSkill,
            'status' => Session::STATUS_PENDING,
            'scheduledAt' => $this->nextMondayAt(19, 0),
            'duration' => 90,
            'location' => 'Google Meet',
            'notes' => 'Bases de Figma pour débutants',
        ]);

        SessionFactory::createOne([
            'mentor' => $julie,
            'student' => $thomas,
            'skill' => $seoSkill,
            'status' => Session::STATUS_COMPLETED,
            'scheduledAt' => $this->nextMondayAt(17, 0)->modify('-7 days'),
            'duration' => 60,
            'location' => 'Zoom',
            'notes' => 'SEO avancé - Optimisation on-page',
        ]);

        SessionFactory::createOne([
            'mentor' => $thomas,
            'student' => $sarah,
            'skill' => $phpSkill,
            'status' => Session::STATUS_CONFIRMED,
            'scheduledAt' => $this->nextMondayAt(19, 0)->modify('+7 days'),
            'duration' => 120,
            'location' => 'En personne - Paris',
            'notes' => 'Symfony avancé - Doctrine et performances',
        ]);

        SessionFactory::createOne([
            'mentor' => $lea,
            'student' => $marc,
            'skill' => $uxSkill,
            'status' => Session::STATUS_CANCELLED,
            'scheduledAt' => $this->nextMondayAt(17, 0)->modify('-14 days'),
            'duration' => 60,
            'location' => 'Discord',
            'notes' => 'Session annulée par le mentor',
        ]);

        SessionFactory::createMany(15, function() {
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

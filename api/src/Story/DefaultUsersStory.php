<?php

namespace App\Story;

use App\Entity\MentorAvailabilityRule;
use App\Factory\MentorAvailabilityRuleFactory;
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
            'profiles' => ['mentor', 'student']
        ]);

        echo "Admin créé: admin@leenup.com / admin123\n";

        UserFactory::createOne([
            'email' => 'admin2@leenup.com',
            'roles' => ['ROLE_ADMIN', 'ROLE_USER'],
            'plainPassword' => 'admin123',
            'isMentor' => false,
            'profiles' => ['mentor']
        ]);

        // Quelques professeurs / formateurs réalistes (mentors)
        UserFactory::createOne([
            'email' => 'sarah.dev@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
            'profiles' => ['student']
        ]);

        UserFactory::createOne([
            'email' => 'marc.design@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
            'profiles' => ['mentor']
        ]);

        UserFactory::createOne([
            'email' => 'julie.marketing@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
            'profiles' => ['mentor']
        ]);

        UserFactory::createOne([
            'email' => 'thomas.fullstack@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
            'profiles' => ['mentor', 'student']
        ]);

        UserFactory::createOne([
            'email' => 'lea.ux@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'password',
            'isMentor' => true,
            'profiles' => ['mentor']
        ]);

        // Disponibilités par défaut pour les mentors seedés
        $this->addDefaultMentorAvailabilities();

        // Utilisateur de test simple (plutôt côté élève)
        UserFactory::createOne([
            'email' => 'user@leenup.com',
            'roles' => ['ROLE_USER'],
            'plainPassword' => 'user123',
            'isMentor' => false,
            'profiles' => ['mentor', 'student']
        ]);

        echo "User de test créé: user@leenup.com / user123\n";

        // 15 utilisateurs aléatoires (élèves/profs) avec tous les nouveaux champs fournis par la factory
        UserFactory::createMany(15);

        echo "✅ " . UserFactory::count() . " users créées\n";
    }

    private function addDefaultMentorAvailabilities(): void
    {
        $baseStart = new \DateTimeImmutable('now');
        $baseEnd = $baseStart->modify('+3 months');

        $rulesByMentor = [
            // Profil mixte: récurrence + one-shot + exception hebdo
            'sarah.dev@leenup.com' => [
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 1,
                    'startTime' => new \DateTimeImmutable('1970-01-01 17:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 20:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
                    'startsAt' => $baseStart,
                    'endsAt' => $baseEnd,
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
                    'dayOfWeek' => 1,
                    'startTime' => new \DateTimeImmutable('1970-01-01 18:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 19:00:00'),
                ],
            ],
            // Plutôt récurrence jours ouvrés
            'marc.design@leenup.com' => [
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 2,
                    'startTime' => new \DateTimeImmutable('1970-01-01 09:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 12:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 4,
                    'startTime' => new \DateTimeImmutable('1970-01-01 14:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 18:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
                    'startsAt' => $baseStart->modify('+3 weeks'),
                    'endsAt' => $baseStart->modify('+3 weeks +2 days'),
                ],
            ],
            // Plutôt one-shot (planning ponctuel)
            'julie.marketing@leenup.com' => [
                [
                    'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
                    'startsAt' => $baseStart->modify('+1 day 10:00'),
                    'endsAt' => $baseStart->modify('+1 day 13:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
                    'startsAt' => $baseStart->modify('+4 days 15:00'),
                    'endsAt' => $baseStart->modify('+4 days 18:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
                    'dayOfWeek' => 5,
                    'startTime' => new \DateTimeImmutable('1970-01-01 16:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 18:00:00'),
                ],
            ],
            // Fullstack: grosses plages récurrentes + exception maintenance
            'thomas.fullstack@leenup.com' => [
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 1,
                    'startTime' => new \DateTimeImmutable('1970-01-01 19:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 22:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 3,
                    'startTime' => new \DateTimeImmutable('1970-01-01 19:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 22:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
                    'startsAt' => $baseStart->modify('+10 days 19:00'),
                    'endsAt' => $baseStart->modify('+10 days 22:00'),
                ],
            ],
            // UX: mix récurrence + one-shot atelier
            'lea.ux@leenup.com' => [
                [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 2,
                    'startTime' => new \DateTimeImmutable('1970-01-01 17:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 19:00:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
                    'startsAt' => $baseStart->modify('+2 weeks 09:00'),
                    'endsAt' => $baseStart->modify('+2 weeks 17:00'),
                ],
                [
                    'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
                    'dayOfWeek' => 2,
                    'startTime' => new \DateTimeImmutable('1970-01-01 18:00:00'),
                    'endTime' => new \DateTimeImmutable('1970-01-01 18:30:00'),
                ],
            ],
        ];

        foreach ($rulesByMentor as $email => $rules) {
            $mentor = UserFactory::find(['email' => $email]);

            foreach ($rules as $rule) {
                MentorAvailabilityRuleFactory::createOne(array_merge([
                    'mentor' => $mentor,
                    'dayOfWeek' => null,
                    'startTime' => null,
                    'endTime' => null,
                    'startsAt' => null,
                    'endsAt' => null,
                ], $rule));
            }
        }
    }
}

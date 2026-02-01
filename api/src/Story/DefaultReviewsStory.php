<?php

namespace App\Story;

use App\Entity\Session;
use App\Factory\ReviewFactory;
use App\Factory\SessionFactory;
use Zenstruck\Foundry\Story;

final class DefaultReviewsStory extends Story
{
    public function build(): void
    {
        echo "Création de reviews réalistes...\n";

        // Récupérer les sessions completed
        $completedSessions = SessionFactory::findBy(['status' => Session::STATUS_COMPLETED]);

        if (count($completedSessions) === 0) {
            echo "⚠️  Aucune session completed trouvée, création de reviews aléatoires...\n";

            // Créer quelques reviews aléatoires
            ReviewFactory::createMany(5, function() {
                return [
                    'rating' => self::faker()->numberBetween(3, 5),
                    'comment' => self::faker()->randomElement([
                        'Excellent mentor, très pédagogue !',
                        'Super session, j\'ai beaucoup appris.',
                        'Merci pour cette formation de qualité.',
                        'Session très productive, je recommande !',
                        'Explications claires et précises.',
                    ]),
                ];
            });
        } else {
            // Créer des reviews pour les sessions completed
            foreach ($completedSessions as $session) {
                // 70% de chance qu'une session completed ait une review
                if (self::faker()->boolean(70)) {
                    ReviewFactory::createOne([
                        'session' => $session,
                        'reviewer' => $session->getStudent(),
                        'rating' => self::faker()->numberBetween(3, 5),
                        'comment' => self::faker()->optional(0.8)->randomElement([
                            'Excellent mentor, très pédagogue !',
                            'Super session, j\'ai beaucoup appris.',
                            'Merci pour cette formation de qualité.',
                            'Session très productive, je recommande !',
                            'Explications claires et précises.',
                            'Bon accompagnement, mentor à l\'écoute.',
                            'Formation de qualité, content du résultat.',
                            null, // Pas de commentaire
                        ]),
                    ]);
                }
            }
        }

        echo "✅ " . ReviewFactory::count() . " reviews créées\n";
    }

    private static function faker()
    {
        return \Faker\Factory::create('fr_FR');
    }
}

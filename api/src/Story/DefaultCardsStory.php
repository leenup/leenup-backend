<?php

namespace App\Story;

use App\Factory\CardFactory;
use Zenstruck\Foundry\Story;

final class DefaultCardsStory extends Story
{
    public function build(): void
    {
        echo "Création des cartes de base (cards)...\n";

        // ================================
        // Famille : mentor_sessions
        // ================================

        // Niveau 1 : Mentor débutant
        CardFactory::createOne([
            'code' => 'mentor_sessions_lv1',
            'family' => 'mentor_sessions',
            'title' => 'Mentor débutant',
            'subtitle' => 'Tu as donné ta première session.',
            'description' => 'Carte obtenue après avoir complété ta première session en tant que mentor.',
            'category' => 'mentoring',
            'level' => 1,
            'imageUrl' => '/images/cards/mentor_lv1.png',
            'conditions' => [
                'type' => 'sessions_given',
                'operator' => '>=',
                'value' => 1,
            ],
            'isActive' => true,
        ]);

        // Niveau 2 : Mentor aguerri
        CardFactory::createOne([
            'code' => 'mentor_sessions_lv2',
            'family' => 'mentor_sessions',
            'title' => 'Mentor aguerri',
            'subtitle' => 'Tu commences à accumuler de l’expérience.',
            'description' => 'Carte obtenue après avoir complété 10 sessions en tant que mentor.',
            'category' => 'mentoring',
            'level' => 2,
            'imageUrl' => '/images/cards/mentor_lv2.png',
            'conditions' => [
                'type' => 'sessions_given',
                'operator' => '>=',
                'value' => 10,
            ],
            'isActive' => true,
        ]);

        // Niveau 3 : Mentor expert
        CardFactory::createOne([
            'code' => 'mentor_sessions_lv3',
            'family' => 'mentor_sessions',
            'title' => 'Mentor expert',
            'subtitle' => 'Une référence pour les apprenants.',
            'description' => 'Carte obtenue après avoir complété 25 sessions en tant que mentor.',
            'category' => 'mentoring',
            'level' => 3,
            'imageUrl' => '/images/cards/mentor_lv3.png',
            'conditions' => [
                'type' => 'sessions_given',
                'operator' => '>=',
                'value' => 25,
            ],
            'isActive' => true,
        ]);

        // ================================
        // Famille : student_sessions
        // ================================

        // Niveau 1 : Apprenant motivé
        CardFactory::createOne([
            'code' => 'student_sessions_lv1',
            'family' => 'student_sessions',
            'title' => 'Apprenant motivé',
            'subtitle' => 'Tu as suivi ta première session.',
            'description' => 'Carte obtenue après avoir complété ta première session en tant qu’apprenant.',
            'category' => 'learning',
            'level' => 1,
            'imageUrl' => '/images/cards/student_lv1.png',
            'conditions' => [
                'type' => 'sessions_taken',
                'operator' => '>=',
                'value' => 1,
            ],
            'isActive' => true,
        ]);

        // Niveau 2 : Apprenant régulier
        CardFactory::createOne([
            'code' => 'student_sessions_lv2',
            'family' => 'student_sessions',
            'title' => 'Apprenant régulier',
            'subtitle' => 'Tu progresses de façon sérieuse.',
            'description' => 'Carte obtenue après avoir complété 5 sessions en tant qu’apprenant.',
            'category' => 'learning',
            'level' => 2,
            'imageUrl' => '/images/cards/student_lv2.png',
            'conditions' => [
                'type' => 'sessions_taken',
                'operator' => '>=',
                'value' => 5,
            ],
            'isActive' => true,
        ]);

        // ================================
        // Famille : reviews_received
        // ================================

        CardFactory::createOne([
            'code' => 'reviews_received_lv1',
            'family' => 'reviews',
            'title' => 'Mentor apprécié',
            'subtitle' => 'Les apprenants aiment travailler avec toi.',
            'description' => 'Carte obtenue après avoir reçu au moins 3 avis (reviews) en tant que mentor.',
            'category' => 'community',
            'level' => 1,
            'imageUrl' => '/images/cards/reviews_lv1.png',
            'conditions' => [
                'type' => 'reviews_received',
                'operator' => '>=',
                'value' => 3,
            ],
            'isActive' => true,
        ]);

        // ================================
        // Famille : engagement (conditions composées)
        // ================================

        CardFactory::createOne([
            'code' => 'engagement_profile_completed',
            'family' => 'engagement',
            'title' => 'Profil complété',
            'subtitle' => 'Ton profil est prêt pour rencontrer la communauté.',
            'description' => 'Carte obtenue après avoir complété ton profil (avatar, bio, au moins 3 compétences).',
            'category' => 'engagement',
            'level' => 1,
            'imageUrl' => '/images/cards/profile_completed.png',
            'conditions' => [
                'all' => [
                    [
                        'type' => 'has_avatar',
                        'value' => true,
                    ],
                    [
                        'type' => 'has_bio',
                        'value' => true,
                    ],
                    [
                        'type' => 'skills_count',
                        'operator' => '>=',
                        'value' => 3,
                    ],
                ],
            ],
            'isActive' => true,
        ]);

        echo "✅ " . CardFactory::count() . " cartes créées\n";
    }
}

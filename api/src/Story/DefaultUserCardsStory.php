<?php

namespace App\Story;

use App\Factory\CardFactory;
use App\Factory\UserCardFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class DefaultUserCardsStory extends Story
{
    public function build(): void
    {
        echo "Création de cartes utilisateur (user cards)...\n";

        // On s'assure que les cartes existent
        self::load(DefaultCardsStory::class);

        // Récupération de quelques utilisateurs connus (comme dans DefaultConversationsStory)
        $admin = UserFactory::find(['email' => 'admin@leenup.com']);
        $user = UserFactory::find(['email' => 'user@leenup.com']);
        $sarah = UserFactory::find(['email' => 'sarah.dev@leenup.com']);
        $marc = UserFactory::find(['email' => 'marc.design@leenup.com']);
        $julie = UserFactory::find(['email' => 'julie.marketing@leenup.com']);

        // Récupération des cartes par code
        $mentorLv1 = CardFactory::find(['code' => 'mentor_sessions_lv1']);
        $mentorLv2 = CardFactory::find(['code' => 'mentor_sessions_lv2']);
        $mentorLv3 = CardFactory::find(['code' => 'mentor_sessions_lv3']);

        $studentLv1 = CardFactory::find(['code' => 'student_sessions_lv1']);
        $studentLv2 = CardFactory::find(['code' => 'student_sessions_lv2']);

        $reviewsLv1 = CardFactory::find(['code' => 'reviews_received_lv1']);
        $profileCompleted = CardFactory::find(['code' => 'engagement_profile_completed']);

        // ================================
        // Cartes pour l'admin
        // ================================
        if ($admin) {
            // Admin = mentor déjà bien avancé
            UserCardFactory::new()->create([
                'user' => $admin,
                'card' => $mentorLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Admin – premières sessions données',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $admin,
                'card' => $mentorLv2,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Admin – plusieurs sessions données',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $admin,
                'card' => $profileCompleted,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Admin – profil complété',
                ],
            ]);
        }

        // ================================
        // Cartes pour Sarah (mentor très actif)
        // ================================
        if ($sarah) {
            UserCardFactory::new()->create([
                'user' => $sarah,
                'card' => $mentorLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Sarah – mentor React débutant',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $sarah,
                'card' => $mentorLv2,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Sarah – mentor aguerri',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $sarah,
                'card' => $mentorLv3,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Sarah – mentor expert',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $sarah,
                'card' => $reviewsLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Sarah – plusieurs reviews reçues',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $sarah,
                'card' => $profileCompleted,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Sarah – profil complet',
                ],
            ]);
        }

        // ================================
        // Cartes pour Marc (mentor design)
        // ================================
        if ($marc) {
            UserCardFactory::new()->create([
                'user' => $marc,
                'card' => $mentorLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Marc – premières sessions design',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $marc,
                'card' => $profileCompleted,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Marc – profil complété',
                ],
            ]);
        }

        // ================================
        // Cartes pour Julie (mentor SEO)
        // ================================
        if ($julie) {
            UserCardFactory::new()->create([
                'user' => $julie,
                'card' => $mentorLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Julie – première session SEO',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $julie,
                'card' => $reviewsLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'Julie – feedbacks positifs',
                ],
            ]);
        }

        // ================================
        // Cartes pour l'user "classique" (plutôt apprenant)
        // ================================
        if ($user) {
            UserCardFactory::new()->create([
                'user' => $user,
                'card' => $studentLv1,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'User – première session suivie',
                ],
            ]);

            UserCardFactory::new()->create([
                'user' => $user,
                'card' => $profileCompleted,
                'source' => 'fixtures',
                'meta' => [
                    'note' => 'User – profil complété pour tester l’affichage',
                ],
            ]);
        }

        echo "✅ " . UserCardFactory::count() . " user cards créées\n";
    }
}

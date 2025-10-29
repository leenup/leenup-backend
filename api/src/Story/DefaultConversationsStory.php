<?php

namespace App\Story;

use App\Factory\ConversationFactory;
use App\Factory\MessageFactory;
use App\Factory\UserFactory;
use Zenstruck\Foundry\Story;

final class DefaultConversationsStory extends Story
{
    public function build(): void
    {
        echo "Création de conversations réalistes...\n";

        $admin = UserFactory::find(['email' => 'admin@leenup.com']);
        $user = UserFactory::find(['email' => 'user@leenup.com']);
        $sarah = UserFactory::find(['email' => 'sarah.dev@leenup.com']);
        $marc = UserFactory::find(['email' => 'marc.design@leenup.com']);
        $julie = UserFactory::find(['email' => 'julie.marketing@leenup.com']);

        // ========================================
        // Conversation ADMIN <-> Sarah (React)
        // ========================================
        $convAdmin1 = ConversationFactory::createOne([
            'participant1' => $admin,
            'participant2' => $sarah,
            'session' => null,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $admin,
            'content' => 'Salut Sarah ! Tu pourrais m\'aider avec React hooks ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $sarah,
            'content' => 'Bien sûr ! C\'est quoi ton problème exactement ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $admin,
            'content' => 'Je n\'arrive pas à bien gérer useEffect avec des dépendances complexes',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $sarah,
            'content' => 'Ah oui, c\'est un piège classique ! Tu veux qu\'on fasse une session rapide demain ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $admin,
            'content' => 'Carrément ! 14h ça te va ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin1,
            'sender' => $sarah,
            'content' => 'Parfait ! Je t\'envoie le lien Zoom demain matin 👍',
            'read' => false,
        ]);

        $convAdmin1->setLastMessageAt(new \DateTimeImmutable('-10 minutes'));

        // ========================================
        // Conversation ADMIN <-> Marc (Design)
        // ========================================
        $convAdmin2 = ConversationFactory::createOne([
            'participant1' => $admin,
            'participant2' => $marc,
            'session' => null,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin2,
            'sender' => $admin,
            'content' => 'Salut Marc ! J\'ai vu ton portfolio, c\'est vraiment stylé 🔥',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin2,
            'sender' => $marc,
            'content' => 'Merci beaucoup ! Tu bosses sur quoi en ce moment ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin2,
            'sender' => $admin,
            'content' => 'Je refais le design de mon appli, et franchement j\'aurais bien besoin de tes conseils',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin2,
            'sender' => $marc,
            'content' => 'Avec plaisir ! Envoie-moi des screenshots, je te fais un retour 👍',
            'read' => false,
        ]);

        $convAdmin2->setLastMessageAt(new \DateTimeImmutable('-1 hour'));

        // ========================================
        // Conversation ADMIN <-> Julie (SEO)
        // ========================================
        $convAdmin3 = ConversationFactory::createOne([
            'participant1' => $admin,
            'participant2' => $julie,
            'session' => null,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin3,
            'sender' => $julie,
            'content' => 'Hello ! Tu cherches toujours quelqu\'un pour t\'aider sur le SEO ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin3,
            'sender' => $admin,
            'content' => 'Oui carrément ! Mon site est invisible sur Google 😅',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $convAdmin3,
            'sender' => $julie,
            'content' => 'On va arranger ça ! Déjà, tu as vérifié tes meta descriptions ?',
            'read' => false,
        ]);

        $convAdmin3->setLastMessageAt(new \DateTimeImmutable('-3 hours'));

        // ========================================
        // Conversation user <-> sarah
        // ========================================
        $conv1 = ConversationFactory::createOne([
            'participant1' => $user,
            'participant2' => $sarah,
            'session' => null,
        ]);

        MessageFactory::createOne([
            'conversation' => $conv1,
            'sender' => $user,
            'content' => 'Salut Sarah, tu es dispo pour une session React ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $conv1,
            'sender' => $sarah,
            'content' => 'Oui bien sûr ! Quand tu veux. Tu préfères quoi comme créneau ?',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $conv1,
            'sender' => $user,
            'content' => 'Jeudi 14h ça te va ?',
            'read' => false,
        ]);

        $conv1->setLastMessageAt(new \DateTimeImmutable('-5 minutes'));

        // ========================================
        // Conversation user <-> julie
        // ========================================
        $conv2 = ConversationFactory::createOne([
            'participant1' => $user,
            'participant2' => $julie,
            'session' => null,
        ]);

        MessageFactory::createOne([
            'conversation' => $conv2,
            'sender' => $user,
            'content' => 'Salut Julie, j\'ai une question sur le SEO...',
            'read' => true,
        ]);

        MessageFactory::createOne([
            'conversation' => $conv2,
            'sender' => $julie,
            'content' => 'Vas-y, je t\'écoute !',
            'read' => true,
        ]);

        $conv2->setLastMessageAt(new \DateTimeImmutable('-1 day'));

        // ========================================
        // 🔧 CONVERSATIONS ALÉATOIRES (CORRIGÉES)
        // ========================================
        $this->createRandomConversations(5);

        echo "✅ " . ConversationFactory::count() . " conversations créées\n";
        echo "✅ " . MessageFactory::count() . " messages créés\n";
    }

    /**
     * Crée des conversations aléatoires en évitant les doublons et l'auto-conversation
     */
    private function createRandomConversations(int $count): void
    {
        // Récupérer tous les utilisateurs existants
        $allUsers = UserFactory::all();

        if (count($allUsers) < 2) {
            echo "⚠️  Pas assez d'utilisateurs pour créer des conversations aléatoires\n";
            return;
        }

        // Récupérer les paires déjà existantes pour éviter les doublons
        $existingPairs = [];
        $existingConversations = ConversationFactory::all();

        foreach ($existingConversations as $conv) {
            $id1 = $conv->getParticipant1()->getId();
            $id2 = $conv->getParticipant2()->getId();

            // Normaliser la paire (toujours le plus petit ID en premier)
            $minId = min($id1, $id2);
            $maxId = max($id1, $id2);
            $existingPairs[] = "{$minId}-{$maxId}";
        }

        $created = 0;
        $attempts = 0;
        $maxAttempts = $count * 10; // Éviter une boucle infinie

        while ($created < $count && $attempts < $maxAttempts) {
            $attempts++;

            // Sélectionner 2 utilisateurs aléatoires
            $user1 = $allUsers[array_rand($allUsers)];
            $user2 = $allUsers[array_rand($allUsers)];

            // Vérifier qu'ils sont différents
            if ($user1->getId() === $user2->getId()) {
                continue;
            }

            // Normaliser la paire
            $id1 = $user1->getId();
            $id2 = $user2->getId();
            $minId = min($id1, $id2);
            $maxId = max($id1, $id2);
            $pairKey = "{$minId}-{$maxId}";

            // Vérifier si cette paire existe déjà
            if (in_array($pairKey, $existingPairs)) {
                continue;
            }

            // Déterminer participant1 et participant2 (ID le plus petit en premier)
            $participant1 = $id1 === $minId ? $user1 : $user2;
            $participant2 = $id1 === $maxId ? $user1 : $user2;

            // Créer la conversation
            $conversation = ConversationFactory::createOne([
                'participant1' => $participant1,
                'participant2' => $participant2,
                'session' => null,
            ]);

            // Créer 1-3 messages aléatoires
            $messageCount = rand(1, 3);
            for ($i = 0; $i < $messageCount; $i++) {
                $sender = $i % 2 === 0 ? $participant1 : $participant2;

                MessageFactory::createOne([
                    'conversation' => $conversation,
                    'sender' => $sender,
                    'content' => $this->getRandomMessageContent(),
                    'read' => rand(0, 1) === 1,
                ]);
            }

            // Marquer cette paire comme créée
            $existingPairs[] = $pairKey;
            $created++;
        }

        if ($created < $count) {
            echo "⚠️  Seulement {$created}/{$count} conversations aléatoires créées (pas assez d'utilisateurs uniques)\n";
        }
    }

    private function getRandomMessageContent(): string
    {
        $messages = [
            "Bonjour ! Comment allez-vous ?",
            "Je suis disponible pour une session la semaine prochaine.",
            "Merci pour votre aide, c'était très utile !",
            "Pouvez-vous me donner plus de détails ?",
            "Parfait, je vous recontacte bientôt.",
            "Avez-vous des disponibilités cette semaine ?",
            "Super, merci pour votre réponse rapide !",
            "Je confirme notre rendez-vous.",
            "Désolé, je dois reporter notre session.",
            "C'était un plaisir d'échanger avec vous !",
        ];

        return $messages[array_rand($messages)];
    }
}

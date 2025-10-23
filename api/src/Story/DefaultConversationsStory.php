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

        // Quelques conversations aléatoires
        ConversationFactory::createMany(5, function() {
            return [
                'participant1' => UserFactory::random(),
                'participant2' => UserFactory::random(),
            ];
        });

        echo "✅ " . ConversationFactory::count() . " conversations créées\n";
        echo "✅ " . MessageFactory::count() . " messages créés\n";
    }
}

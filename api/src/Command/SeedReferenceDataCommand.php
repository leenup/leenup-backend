<?php

namespace App\Command;

use App\Entity\Card;
use App\Entity\Category;
use App\Entity\Skill;
use App\Repository\CardRepository;
use App\Repository\CategoryRepository;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-reference-data',
    description: 'Seed production-safe reference data (categories, skills, cards) without fixtures bundle',
)]
class SeedReferenceDataCommand extends Command
{
    private const CATEGORIES = [
        'Création & Design',
        'Outils & Production',
        'Communication & SoftSkills',
    ];

    private const SKILLS_BY_CATEGORY = [
        'Création & Design' => [
            'Graphisme',
            'UI/UX Design',
            'Illustration',
            'Branding',
            'Edition',
            'IA Créative',
        ],

        'Outils & Production' => [
            'Figma',
            'Adobe',
            'Animation',
            'Notion',
            'Production',
        ],

        'Communication & SoftSkills' => [
            'Prise de parole',
            'Storytelling',
            'Rédaction',
            'Organisation',
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const CARDS = [
        [
            'code' => 'mentor_sessions_lv1',
            'family' => 'mentor_sessions',
            'title' => 'Mentor débutant',
            'subtitle' => 'Tu as donné ta première session.',
            'description' => 'Carte obtenue après avoir complété ta première session en tant que mentor.',
            'category' => 'mentoring',
            'level' => 1,
            'imageUrl' => '/images/cards/mentor_lv1.png',
            'conditions' => ['type' => 'sessions_given', 'operator' => '>=', 'value' => 1],
            'isActive' => true,
        ],
        [
            'code' => 'mentor_sessions_lv2',
            'family' => 'mentor_sessions',
            'title' => 'Mentor aguerri',
            'subtitle' => 'Tu commences à accumuler de l’expérience.',
            'description' => 'Carte obtenue après avoir complété 10 sessions en tant que mentor.',
            'category' => 'mentoring',
            'level' => 2,
            'imageUrl' => '/images/cards/mentor_lv2.png',
            'conditions' => ['type' => 'sessions_given', 'operator' => '>=', 'value' => 10],
            'isActive' => true,
        ],
        [
            'code' => 'mentor_sessions_lv3',
            'family' => 'mentor_sessions',
            'title' => 'Mentor expert',
            'subtitle' => 'Une référence pour les apprenants.',
            'description' => 'Carte obtenue après avoir complété 25 sessions en tant que mentor.',
            'category' => 'mentoring',
            'level' => 3,
            'imageUrl' => '/images/cards/mentor_lv3.png',
            'conditions' => ['type' => 'sessions_given', 'operator' => '>=', 'value' => 25],
            'isActive' => true,
        ],
        [
            'code' => 'student_sessions_lv1',
            'family' => 'student_sessions',
            'title' => 'Apprenant motivé',
            'subtitle' => 'Tu as suivi ta première session.',
            'description' => 'Carte obtenue après avoir complété ta première session en tant qu’apprenant.',
            'category' => 'learning',
            'level' => 1,
            'imageUrl' => '/images/cards/student_lv1.png',
            'conditions' => ['type' => 'sessions_taken', 'operator' => '>=', 'value' => 1],
            'isActive' => true,
        ],
        [
            'code' => 'student_sessions_lv2',
            'family' => 'student_sessions',
            'title' => 'Apprenant régulier',
            'subtitle' => 'Tu progresses de façon sérieuse.',
            'description' => 'Carte obtenue après avoir complété 5 sessions en tant qu’apprenant.',
            'category' => 'learning',
            'level' => 2,
            'imageUrl' => '/images/cards/student_lv2.png',
            'conditions' => ['type' => 'sessions_taken', 'operator' => '>=', 'value' => 5],
            'isActive' => true,
        ],
        [
            'code' => 'reviews_received_lv1',
            'family' => 'reviews',
            'title' => 'Mentor apprécié',
            'subtitle' => 'Les apprenants aiment travailler avec toi.',
            'description' => 'Carte obtenue après avoir reçu au moins 3 avis (reviews) en tant que mentor.',
            'category' => 'community',
            'level' => 1,
            'imageUrl' => '/images/cards/reviews_lv1.png',
            'conditions' => ['type' => 'reviews_received', 'operator' => '>=', 'value' => 3],
            'isActive' => true,
        ],
        [
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
                    ['type' => 'has_avatar', 'value' => true],
                    ['type' => 'has_bio', 'value' => true],
                    ['type' => 'skills_count', 'operator' => '>=', 'value' => 3],
                ],
            ],
            'isActive' => true,
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepository $categoryRepository,
        private readonly SkillRepository $skillRepository,
        private readonly CardRepository $cardRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $createdCategories = $this->seedCategories();

        $this->entityManager->flush();

        $createdSkills = $this->seedSkills();
        $createdCards = $this->seedCards();

        $this->entityManager->flush();

        $io->success(sprintf(
            'Reference data seeding done. Categories +%d, Skills +%d, Cards +%d.',
            $createdCategories,
            $createdSkills,
            $createdCards
        ));

        return Command::SUCCESS;
    }

    private function seedCategories(): int
    {
        $created = 0;

        foreach (self::CATEGORIES as $title) {
            if ($this->categoryRepository->findOneBy(['title' => $title]) instanceof Category) {
                continue;
            }

            $category = new Category();
            $category->setTitle($title);

            $this->entityManager->persist($category);
            ++$created;
        }

        return $created;
    }

    private function seedSkills(): int
    {
        $created = 0;

        foreach (self::SKILLS_BY_CATEGORY as $categoryTitle => $skills) {
            $category = $this->categoryRepository->findOneBy(['title' => $categoryTitle]);

            if (!$category instanceof Category) {
                continue;
            }

            foreach ($skills as $skillTitle) {
                if ($this->skillRepository->findOneBy([
                        'title' => $skillTitle,
                        'category' => $category,
                    ]) instanceof Skill) {
                    continue;
                }

                $skill = new Skill();
                $skill->setTitle($skillTitle);
                $skill->setCategory($category);

                $this->entityManager->persist($skill);
                ++$created;
            }
        }

        return $created;
    }

    private function seedCards(): int
    {
        $created = 0;

        foreach (self::CARDS as $cardData) {
            if ($this->cardRepository->findOneBy(['code' => $cardData['code']]) instanceof Card) {
                continue;
            }

            $card = new Card();
            $card
                ->setCode($cardData['code'])
                ->setFamily($cardData['family'])
                ->setTitle($cardData['title'])
                ->setSubtitle($cardData['subtitle'])
                ->setDescription($cardData['description'])
                ->setCategory($cardData['category'])
                ->setLevel($cardData['level'])
                ->setImageUrl($cardData['imageUrl'])
                ->setConditions($cardData['conditions'])
                ->setIsActive((bool) $cardData['isActive']);

            $this->entityManager->persist($card);
            ++$created;
        }

        return $created;
    }
}

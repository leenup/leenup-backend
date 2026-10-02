<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Category;
use App\Entity\Session;
use App\Entity\Skill;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\ReviewFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MentorTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private User $currentUser;

    private Category $devCategory;
    private Category $designCategory;
    private Skill $react;
    private Skill $symfony;
    private Skill $figma;

    protected function setUp(): void
    {
        parent::setUp();

        $this->devCategory = CategoryFactory::createOne(['title' => 'Development']);
        $this->designCategory = CategoryFactory::createOne(['title' => 'Design']);
        $this->react = SkillFactory::createOne(['title' => 'React', 'category' => $this->devCategory]);
        $this->symfony = SkillFactory::createOne(['title' => 'Symfony', 'category' => $this->devCategory]);
        $this->figma = SkillFactory::createOne(['title' => 'Figma', 'category' => $this->designCategory]);

        [$this->client, , $this->currentUser] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-search'),
            password: 'password',
        );
    }

    // ========================================
    // HELPERS
    // ========================================

    private function createMentor(array $skills, array $attributes = []): User
    {
        $mentor = UserFactory::createOne(array_merge([
            'email' => $this->uniqueEmail('mentor'),
            'isActive' => true,
        ], $attributes));

        foreach ($skills as $skill) {
            UserSkillFactory::createOne([
                'owner' => $mentor,
                'skill' => $skill,
                'type' => UserSkill::TYPE_TEACH,
                'level' => UserSkill::LEVEL_EXPERT,
            ]);
        }

        return $mentor;
    }

    /**
     * @return int[]
     */
    private function searchIds(string $query = ''): array
    {
        $response = $this->client->request('GET', '/mentors'.($query !== '' ? '?'.$query : ''));
        self::assertSame(200, $response->getStatusCode());

        return array_map(static fn (array $mentor): int => $mentor['id'], $response->toArray()['member']);
    }

    // ========================================
    // LISTE
    // ========================================

    public function testListRequiresAuthentication(): void
    {
        $response = static::createClient()->request('GET', '/mentors');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testListOnlyReturnsActiveUsersTeachingASkill(): void
    {
        $mentor = $this->createMentor([$this->react]);
        $this->createMentor([$this->react], ['isActive' => false]);

        // Flag isMentor à true mais plus aucune compétence enseignée : ne doit pas apparaître.
        $learner = UserFactory::createOne(['email' => $this->uniqueEmail('learner'), 'isMentor' => true]);
        UserSkillFactory::createOne([
            'owner' => $learner,
            'skill' => $this->react,
            'type' => UserSkill::TYPE_LEARN,
        ]);

        // L'utilisateur courant enseigne aussi, mais ne doit pas se voir lui-même.
        UserSkillFactory::createOne([
            'owner' => $this->currentUser,
            'skill' => $this->react,
            'type' => UserSkill::TYPE_TEACH,
        ]);

        self::assertSame([$mentor->getId()], $this->searchIds());
    }

    public function testListExposesOnlyPublicFields(): void
    {
        $mentor = $this->createMentor([$this->react], [
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'averageRating' => '4.50',
            'reviewCount' => 2,
        ]);
        UserSkillFactory::createOne([
            'owner' => $mentor,
            'skill' => $this->figma,
            'type' => UserSkill::TYPE_LEARN,
        ]);

        $response = $this->client->request('GET', '/mentors');
        $data = $response->toArray();

        self::assertSame(1, $data['totalItems']);
        $item = $data['member'][0];

        self::assertSame('/mentors/'.$mentor->getId(), $item['@id']);
        self::assertSame('Alice', $item['firstName']);
        self::assertSame('Martin', $item['lastName']);
        self::assertEquals(4.5, $item['averageRating']);
        self::assertSame(2, $item['reviewCount']);

        // Seules les compétences enseignées sont listées.
        self::assertCount(1, $item['skills']);
        self::assertSame($this->react->getId(), $item['skills'][0]['skillId']);
        self::assertSame('React', $item['skills'][0]['title']);
        self::assertSame('Development', $item['skills'][0]['categoryTitle']);
        self::assertSame(UserSkill::LEVEL_EXPERT, $item['skills'][0]['level']);

        foreach (['email', 'roles', 'password', 'tokenBalance', 'lastLoginAt', 'birthdate', 'isActive', 'recentReviews'] as $privateField) {
            self::assertArrayNotHasKey($privateField, $item);
        }
    }

    // ========================================
    // FILTRES
    // ========================================

    public function testFilterBySkill(): void
    {
        $reactMentor = $this->createMentor([$this->react]);
        $this->createMentor([$this->symfony]);

        self::assertSame([$reactMentor->getId()], $this->searchIds('skill='.$this->react->getId()));
    }

    public function testFilterByCategory(): void
    {
        $designMentor = $this->createMentor([$this->figma]);
        $this->createMentor([$this->react]);

        self::assertSame([$designMentor->getId()], $this->searchIds('category='.$this->designCategory->getId()));
    }

    public function testSkillFilterIgnoresLearnedSkills(): void
    {
        $mentor = $this->createMentor([$this->react]);
        UserSkillFactory::createOne([
            'owner' => $mentor,
            'skill' => $this->figma,
            'type' => UserSkill::TYPE_LEARN,
        ]);

        self::assertSame([], $this->searchIds('skill='.$this->figma->getId()));
    }

    public function testTextSearchOnNameAndSkillTitle(): void
    {
        $alice = $this->createMentor([$this->react], ['firstName' => 'Alice', 'lastName' => 'Martin']);
        $bob = $this->createMentor([$this->symfony], ['firstName' => 'Bob', 'lastName' => 'Durand']);

        self::assertSame([$alice->getId()], $this->searchIds('q=alice'));
        self::assertSame([$alice->getId()], $this->searchIds('q='.urlencode('alice mar')));
        self::assertSame([$bob->getId()], $this->searchIds('q=SYMF'));
        self::assertSame([], $this->searchIds('q=100%25'));
    }

    public function testFilterByMinRating(): void
    {
        $good = $this->createMentor([$this->react], ['averageRating' => '4.20', 'reviewCount' => 5]);
        $this->createMentor([$this->react], ['averageRating' => '3.90', 'reviewCount' => 5]);
        $this->createMentor([$this->react]);

        self::assertSame([$good->getId()], $this->searchIds('minRating=4'));
    }

    /**
     * @dataProvider invalidQueryProvider
     */
    public function testInvalidQueryReturns400(string $query): void
    {
        $response = $this->client->request('GET', '/mentors?'.$query);

        self::assertSame(400, $response->getStatusCode());
    }

    public static function invalidQueryProvider(): iterable
    {
        yield 'skill not an int' => ['skill=abc'];
        yield 'category negative' => ['category=-1'];
        yield 'minRating too high' => ['minRating=6'];
        yield 'unknown sort field' => ['order[email]=asc'];
        yield 'invalid sort direction' => ['order[averageRating]=up'];
    }

    // ========================================
    // TRI & PAGINATION
    // ========================================

    public function testDefaultSortIsBestRatedFirstAndUnratedLast(): void
    {
        $unrated = $this->createMentor([$this->react]);
        $average = $this->createMentor([$this->react], ['averageRating' => '3.50', 'reviewCount' => 10]);
        $best = $this->createMentor([$this->react], ['averageRating' => '4.80', 'reviewCount' => 1]);
        $bestMoreReviews = $this->createMentor([$this->react], ['averageRating' => '4.80', 'reviewCount' => 4]);

        self::assertSame(
            [$bestMoreReviews->getId(), $best->getId(), $average->getId(), $unrated->getId()],
            $this->searchIds(),
        );
    }

    public function testSortByRatingAscendingKeepsUnratedLast(): void
    {
        $unrated = $this->createMentor([$this->react]);
        $high = $this->createMentor([$this->react], ['averageRating' => '4.80', 'reviewCount' => 1]);
        $low = $this->createMentor([$this->react], ['averageRating' => '2.00', 'reviewCount' => 1]);

        self::assertSame(
            [$low->getId(), $high->getId(), $unrated->getId()],
            $this->searchIds('order[averageRating]=asc'),
        );
    }

    public function testSortByReviewCount(): void
    {
        $few = $this->createMentor([$this->react], ['averageRating' => '5.00', 'reviewCount' => 1]);
        $many = $this->createMentor([$this->react], ['averageRating' => '3.00', 'reviewCount' => 12]);

        self::assertSame([$many->getId(), $few->getId()], $this->searchIds('order[reviewCount]=desc'));
    }

    public function testPagination(): void
    {
        $this->createMentor([$this->react], ['averageRating' => '5.00', 'reviewCount' => 1]);
        $second = $this->createMentor([$this->react], ['averageRating' => '4.00', 'reviewCount' => 1]);
        $this->createMentor([$this->react], ['averageRating' => '3.00', 'reviewCount' => 1]);

        $response = $this->client->request('GET', '/mentors?itemsPerPage=1&page=2');
        $data = $response->toArray();

        self::assertSame(3, $data['totalItems']);
        self::assertCount(1, $data['member']);
        self::assertSame($second->getId(), $data['member'][0]['id']);
        self::assertArrayHasKey('next', $data['view']);
    }

    // ========================================
    // DÉTAIL
    // ========================================

    public function testGetMentorDetailWithRecentReviews(): void
    {
        $mentor = $this->createMentor([$this->react], ['averageRating' => '4.00', 'reviewCount' => 1]);
        $student = UserFactory::createOne([
            'email' => $this->uniqueEmail('student'),
            'firstName' => 'Chloé',
            'lastName' => 'Secret',
        ]);
        $session = SessionFactory::createOne([
            'mentor' => $mentor,
            'student' => $student,
            'skill' => $this->react,
            'status' => Session::STATUS_COMPLETED,
        ]);
        ReviewFactory::createOne([
            'session' => $session,
            'reviewer' => $student,
            'rating' => 4,
            'comment' => 'Très clair',
        ]);

        $response = $this->client->request('GET', '/mentors/'.$mentor->getId());

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray();

        self::assertSame($mentor->getId(), $data['id']);
        self::assertArrayNotHasKey('email', $data);
        self::assertCount(1, $data['recentReviews']);

        $review = $data['recentReviews'][0];
        self::assertSame(4, $review['rating']);
        self::assertSame('Très clair', $review['comment']);
        self::assertSame('Chloé', $review['reviewerFirstName']);
        self::assertArrayNotHasKey('reviewerLastName', $review);
    }

    public function testGetDetailOfUserTeachingNothingReturns404(): void
    {
        $user = UserFactory::createOne(['email' => $this->uniqueEmail('not-mentor'), 'isMentor' => true]);

        $response = $this->client->request('GET', '/mentors/'.$user->getId());

        self::assertSame(404, $response->getStatusCode());
    }

    public function testGetDetailOfInactiveMentorReturns404(): void
    {
        $mentor = $this->createMentor([$this->react], ['isActive' => false]);

        $response = $this->client->request('GET', '/mentors/'.$mentor->getId());

        self::assertSame(404, $response->getStatusCode());
    }
}

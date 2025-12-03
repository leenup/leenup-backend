<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Session;
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

class ReviewTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $studentClient;
    private HttpClientInterface $mentorClient;
    private HttpClientInterface $otherClient;

    private string $studentCsrfToken;
    private string $mentorCsrfToken;
    private string $otherCsrfToken;

    private User $student;
    private User $mentor;
    private User $otherUser;

    /** @var Session */
    private $completedSession;

    protected function setUp(): void
    {
        parent::setUp();

        // Catégorie & skill
        $category = CategoryFactory::createOne(['title' => 'Development']);
        $skill = SkillFactory::createOne([
            'title' => 'React',
            'category' => $category,
        ]);

        // Authentification des 3 users via le trait
        [
            $this->studentClient,
            $this->studentCsrfToken,
            $this->student,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-review'),
            password: 'password',
        );

        [
            $this->mentorClient,
            $this->mentorCsrfToken,
            $this->mentor,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-review'),
            password: 'password',
        );

        [
            $this->otherClient,
            $this->otherCsrfToken,
            $this->otherUser,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('other-review'),
            password: 'password',
        );

        // Lier le mentor au skill (teach)
        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        // Session complétée de base
        $this->completedSession = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $skill,
            'status' => Session::STATUS_COMPLETED,
        ]);
    }

    // ========================================
    // 🆕 HELPER METHOD
    // ========================================

    /**
     * Helper pour créer une review avec une date de création personnalisée
     * Utile pour tester la règle des 7 jours.
     *
     * @param int $daysAgo Nombre de jours dans le passé (ex: 8 = il y a 8 jours)
     */
    private function createOldReview(int $daysAgo): object
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good mentor',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $reviewEntity = $em->getRepository(\App\Entity\Review::class)->find($review->getId());

        $reflectionClass = new \ReflectionClass($reviewEntity);
        $createdAtProperty = $reflectionClass->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue(
            $reviewEntity,
            new \DateTimeImmutable("-{$daysAgo} days")
        );

        $em->flush();
        $em->clear();

        return $review;
    }

    // ========================================
    // TESTS DE LECTURE
    // ========================================

    public function testGetReviewsCollection(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = $this->studentClient->request('GET', '/reviews');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/Review', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetReviewsCollectionWithoutAuth(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/reviews');

        self::assertSame(401, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(401, $data['code'] ?? null);
        self::assertSame('JWT Token not found', $data['message'] ?? null);
    }

    public function testGetReviewById(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = $this->studentClient->request('GET', '/reviews/'.$review->getId());

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame(5, $data['rating'] ?? null);
    }

    // ========================================
    // TESTS DE CRÉATION
    // ========================================

    public function testStudentCanCreateReviewForCompletedSession(): void
    {
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$this->completedSession->getId(),
                    'rating' => 5,
                    'comment' => 'Excellent mentor!',
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame(5, $data['rating'] ?? null);
        self::assertSame('Excellent mentor!', $data['comment'] ?? null);
    }

    public function testCannotReviewNonCompletedSession(): void
    {
        $pendingSession = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'status' => Session::STATUS_PENDING,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$pendingSession->getId(),
                    'rating' => 5,
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        // ⚠️ important : false pour ne pas jeter d’exception sur 4xx
        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $found = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'session'
                && ($violation['message'] ?? null) === 'You can only review a completed session') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Expected violation on session with message "You can only review a completed session".');
    }

    public function testCannotReviewSessionWhereNotStudent(): void
    {
        $response = $this->requestUnsafe(
            $this->otherClient,
            'POST',
            '/reviews',
            $this->otherCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$this->completedSession->getId(),
                    'rating' => 5,
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);

        $violations = $data['violations'] ?? [];
        self::assertNotEmpty($violations);

        $found = false;
        foreach ($violations as $violation) {
            if (($violation['propertyPath'] ?? null) === 'session'
                && ($violation['message'] ?? null) === 'You can only review sessions where you are the student') {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'Expected violation on session with correct message.');
    }

    public function testCannotReviewSameSessionTwice(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$this->completedSession->getId(),
                    'rating' => 4,
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);
    }

    public function testRatingMustBeBetween1And5(): void
    {
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$this->completedSession->getId(),
                    'rating' => 6,
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(422, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('ConstraintViolation', $data['@type'] ?? null);
    }

    // ========================================
    // TESTS DE MODIFICATION
    // ========================================

    public function testStudentCanModifyReviewWithin7Days(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good mentor',
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/reviews/'.$review->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'rating' => 5,
                    'comment' => 'Excellent mentor!',
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame(5, $data['rating'] ?? null);
        self::assertSame('Excellent mentor!', $data['comment'] ?? null);
    }

    public function testStudentCannotModifyReviewAfter7Days(): void
    {
        $review = $this->createOldReview(8);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/reviews/'.$review->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'rating' => 5,
                    'comment' => 'Trying to update old review',
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame(
            'You can only modify your own reviews within 7 days of creation',
            $data['detail'] ?? null
        );
    }

    public function testStudentCannotModifyReviewAtExactly7Days(): void
    {
        $review = $this->createOldReview(7);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'PATCH',
            '/reviews/'.$review->getId(),
            $this->studentCsrfToken,
            [
                'json' => [
                    'rating' => 5,
                    'comment' => 'Trying to update at exactly 7 days',
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('Error', $data['@type'] ?? null);
        self::assertSame(
            'You can only modify your own reviews within 7 days of creation',
            $data['detail'] ?? null
        );
    }

    public function testOtherUserCannotModifyReview(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
        ]);

        $response = $this->requestUnsafe(
            $this->otherClient,
            'PATCH',
            '/reviews/'.$review->getId(),
            $this->otherCsrfToken,
            [
                'json' => [
                    'rating' => 1,
                ],
                'headers' => [
                    'Content-Type' => 'application/merge-patch+json',
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS DE SUPPRESSION
    // ========================================

    public function testStudentCannotDeleteReview(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = $this->requestUnsafe(
            $this->studentClient,
            'DELETE',
            '/reviews/'.$review->getId(),
            $this->studentCsrfToken
        );

        self::assertSame(403, $response->getStatusCode());
    }

    // ========================================
    // TESTS MÉTIER
    // ========================================

    public function testMentorAverageRatingIsUpdated(): void
    {
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/reviews',
            $this->studentCsrfToken,
            [
                'json' => [
                    'session' => '/sessions/'.$this->completedSession->getId(),
                    'rating' => 5,
                ],
                'headers' => [
                    'Content-Type' => 'application/ld+json',
                ],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        // Récupérer le mentor depuis la BDD pour avoir la valeur à jour
        $mentorFromDb = UserFactory::find(['id' => $this->mentor->getId()]);

        self::assertSame('5.00', $mentorFromDb->getAverageRating());
    }

    // ========================================
    // TESTS /me ENDPOINTS
    // ========================================

    public function testGetMyReviewsGiven(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
            'comment' => 'Excellent mentor!',
        ]);

        $response = $this->studentClient->request('GET', '/me/reviews/given');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/MyReviewGiven', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
        self::assertSame(5, $data['member'][0]['rating'] ?? null);
    }

    public function testGetMyReviewsReceived(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good session',
        ]);

        $response = $this->mentorClient->request('GET', '/me/reviews/received');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray();
        self::assertSame('/contexts/MyReviewReceived', $data['@context'] ?? null);
        self::assertSame('Collection', $data['@type'] ?? null);
        self::assertArrayHasKey('totalItems', $data);
        self::assertGreaterThanOrEqual(1, $data['totalItems']);
        self::assertSame(4, $data['member'][0]['rating'] ?? null);
    }

    public function testGetMyReviewsGivenWithoutAuth(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/me/reviews/given');

        self::assertSame(401, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(401, $data['code'] ?? null);
        self::assertSame('JWT Token not found', $data['message'] ?? null);
    }

    public function testGetMyReviewsReceivedWithoutAuth(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/me/reviews/received');

        self::assertSame(401, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(401, $data['code'] ?? null);
        self::assertSame('JWT Token not found', $data['message'] ?? null);
    }
}

<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Session;
use App\Factory\CategoryFactory;
use App\Factory\ReviewFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserFactory;
use App\Factory\UserSkillFactory;
use App\Entity\UserSkill;
use Zenstruck\Foundry\Test\Factories;

class ReviewTest extends ApiTestCase
{
    use Factories;

    private string $studentToken;
    private string $mentorToken;
    private string $otherUserToken;
    private $student;
    private $mentor;
    private $otherUser;
    private $completedSession;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $skill = SkillFactory::createOne(['title' => 'React', 'category' => $category]);

        $this->student = UserFactory::createOne([
            'email' => 'student@test.com',
            'plainPassword' => 'password',
        ]);

        $this->mentor = UserFactory::createOne([
            'email' => 'mentor@test.com',
            'plainPassword' => 'password',
        ]);

        $this->otherUser = UserFactory::createOne([
            'email' => 'other@test.com',
            'plainPassword' => 'password',
        ]);

        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);

        $this->completedSession = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $skill,
            'status' => Session::STATUS_COMPLETED,
        ]);

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'student@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->studentToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'mentor@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->mentorToken = $response->toArray()['token'];

        $response = static::createClient()->request('POST', '/auth', [
            'json' => ['email' => 'other@test.com', 'password' => 'password'],
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->otherUserToken = $response->toArray()['token'];
    }

    // ========================================
    // 🆕 HELPER METHOD
    // ========================================

    /**
     * Helper pour créer une review avec une date de création personnalisée
     * Utile pour tester la règle des 7 jours
     *
     * @param int $daysAgo Nombre de jours dans le passé (ex: 8 = il y a 8 jours)
     * @return object La review créée
     */
    private function createOldReview(int $daysAgo): object
    {
        // Créer la review normalement
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good mentor',
        ]);

        // Récupérer l'EntityManager
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Récupérer l'entité depuis la base de données (méthode universelle)
        $reviewEntity = $entityManager->getRepository(\App\Entity\Review::class)->find($review->getId());

        // Modifier la date de création via Reflection
        $reflectionClass = new \ReflectionClass($reviewEntity);
        $createdAtProperty = $reflectionClass->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue(
            $reviewEntity,
            new \DateTimeImmutable("-{$daysAgo} days")
        );

        // Persister la modification
        $entityManager->flush();
        $entityManager->clear(); // Clear pour forcer le rechargement depuis la BDD

        return $review;
    }

    // ========================================
    // TESTS DE LECTURE (inchangés)
    // ========================================

    public function testGetReviewsCollection(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = static::createClient()->request('GET', '/reviews', [
            'auth_bearer' => $this->studentToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/Review',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
    }

    public function testGetReviewsCollectionWithoutAuth(): void
    {
        static::createClient()->request('GET', '/reviews');
        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetReviewById(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        $response = static::createClient()->request('GET', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->studentToken,
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(5, $data['rating']);
    }

    // ========================================
    // TESTS DE CRÉATION (inchangés)
    // ========================================

    public function testStudentCanCreateReviewForCompletedSession(): void
    {
        $response = static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $this->completedSession->getId(),
                'rating' => 5,
                'comment' => 'Excellent mentor!',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertEquals(5, $data['rating']);
        $this->assertEquals('Excellent mentor!', $data['comment']);
    }

    public function testCannotReviewNonCompletedSession(): void
    {
        $pendingSession = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'status' => Session::STATUS_PENDING,
        ]);

        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $pendingSession->getId(),
                'rating' => 5,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'session',
                    'message' => 'You can only review a completed session',
                ],
            ],
        ]);
    }

    public function testCannotReviewSessionWhereNotStudent(): void
    {
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->otherUserToken,
            'json' => [
                'session' => '/sessions/' . $this->completedSession->getId(),
                'rating' => 5,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
            'violations' => [
                [
                    'propertyPath' => 'session',
                    'message' => 'You can only review sessions where you are the student',
                ],
            ],
        ]);
    }

    public function testCannotReviewSameSessionTwice(): void
    {
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $this->completedSession->getId(),
                'rating' => 4,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
        ]);
    }

    public function testRatingMustBeBetween1And5(): void
    {
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $this->completedSession->getId(),
                'rating' => 6,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'ConstraintViolation',
        ]);
    }

    // ========================================
    // TESTS DE MODIFICATION (avec nouveaux tests)
    // ========================================

    public function testStudentCanModifyReviewWithin7Days(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good mentor',
        ]);

        $response = static::createClient()->request('PATCH', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'rating' => 5,
                'comment' => 'Excellent mentor!',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals(5, $data['rating']);
        $this->assertEquals('Excellent mentor!', $data['comment']);
    }

    /**
     * 🔥 NOUVEAU TEST CRITIQUE : Vérifier qu'on ne peut pas modifier une review après 7 jours
     * Ce test valide la logique du ReviewVoter
     */
    public function testStudentCannotModifyReviewAfter7Days(): void
    {
        // Créer une review vieille de 8 jours
        $review = $this->createOldReview(8);

        // Tenter de modifier la review
        static::createClient()->request('PATCH', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'rating' => 5,
                'comment' => 'Trying to update old review',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // Doit échouer avec 403 (Access Denied)
        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'You can only modify your own reviews within 7 days of creation',
        ]);
    }

    /**
     * 🆕 NOUVEAU TEST : Edge case - Review créée il y a exactement 7 jours
     * À 7 jours pile, c'est DÉJÀ trop tard pour modifier
     */
    public function testStudentCannotModifyReviewAtExactly7Days(): void
    {
        // Créer une review vieille d'exactement 7 jours
        $review = $this->createOldReview(7);

        // Tenter de modifier
        static::createClient()->request('PATCH', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'rating' => 5,
                'comment' => 'Trying to update at exactly 7 days',
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        // À 7 jours exactement, c'est DÉJÀ trop tard → 403
        // Logique métier : "dans les 7 jours" = strictement moins de 7 jours
        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => 'You can only modify your own reviews within 7 days of creation',
        ]);
    }

    public function testOtherUserCannotModifyReview(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
        ]);

        static::createClient()->request('PATCH', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->otherUserToken,
            'json' => [
                'rating' => 1,
            ],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ========================================
    // TESTS DE SUPPRESSION (inchangé)
    // ========================================

    public function testStudentCannotDeleteReview(): void
    {
        $review = ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
        ]);

        static::createClient()->request('DELETE', '/reviews/' . $review->getId(), [
            'auth_bearer' => $this->studentToken,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ========================================
    // TESTS MÉTIER (inchangé)
    // ========================================

    public function testMentorAverageRatingIsUpdated(): void
    {
        // Créer une première review
        static::createClient()->request('POST', '/reviews', [
            'auth_bearer' => $this->studentToken,
            'json' => [
                'session' => '/sessions/' . $this->completedSession->getId(),
                'rating' => 5,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        // Récupérer le mentor depuis la BDD pour avoir la valeur à jour
        $mentorFromDb = UserFactory::find(['id' => $this->mentor->getId()]);

        $this->assertEquals('5.00', $mentorFromDb->getAverageRating());
    }

    // ========================================
    // TESTS /me ENDPOINTS (inchangés)
    // ========================================

    public function testGetMyReviewsGiven(): void
    {
        // Créer une review donnée par le student
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 5,
            'comment' => 'Excellent mentor!',
        ]);

        $response = static::createClient()->request('GET', '/me/reviews/given', [
            'auth_bearer' => $this->studentToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/MyReviewGiven',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
        $this->assertEquals(5, $data['member'][0]['rating']);
    }

    public function testGetMyReviewsReceived(): void
    {
        // Créer une review pour le mentor
        ReviewFactory::createOne([
            'session' => $this->completedSession,
            'reviewer' => $this->student,
            'rating' => 4,
            'comment' => 'Good session',
        ]);

        $response = static::createClient()->request('GET', '/me/reviews/received', [
            'auth_bearer' => $this->mentorToken,
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/contexts/MyReviewReceived',
            '@type' => 'Collection',
        ]);

        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(1, $data['totalItems']);
        $this->assertEquals(4, $data['member'][0]['rating']);
    }

    public function testGetMyReviewsGivenWithoutAuth(): void
    {
        static::createClient()->request('GET', '/me/reviews/given');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetMyReviewsReceivedWithoutAuth(): void
    {
        static::createClient()->request('GET', '/me/reviews/received');

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}

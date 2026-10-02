<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Session;
use App\Entity\TokenTransaction;
use App\Entity\User;
use App\Entity\UserSkill;
use App\Factory\CategoryFactory;
use App\Factory\MentorAvailabilityRuleFactory;
use App\Factory\SessionFactory;
use App\Factory\SkillFactory;
use App\Factory\UserSkillFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MyTokenTransactionsTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $studentClient;
    private HttpClientInterface $mentorClient;
    private string $studentCsrfToken;
    private string $mentorCsrfToken;
    private User $student;
    private User $mentor;
    private $skill;

    protected function setUp(): void
    {
        parent::setUp();

        $category = CategoryFactory::createOne(['title' => 'Development']);
        $this->skill = SkillFactory::createOne(['title' => 'React', 'category' => $category]);

        [$this->studentClient, $this->studentCsrfToken, $this->student] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-tokens'),
            password: 'password',
        );
        [$this->mentorClient, $this->mentorCsrfToken, $this->mentor] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-tokens'),
            password: 'password',
        );

        UserSkillFactory::createOne([
            'owner' => $this->mentor,
            'skill' => $this->skill,
            'type' => UserSkill::TYPE_TEACH,
            'level' => UserSkill::LEVEL_EXPERT,
        ]);
        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => 'one_shot',
            'startsAt' => new \DateTimeImmutable('now'),
            'endsAt' => new \DateTimeImmutable('+3 months'),
        ]);
    }

    // ========================================
    // HELPERS
    // ========================================

    private function bookSession(): string
    {
        $response = $this->requestUnsafe($this->studentClient, 'POST', '/sessions', $this->studentCsrfToken, [
            'json' => [
                'mentor' => '/users/'.$this->mentor->getId(),
                'skill' => '/skills/'.$this->skill->getId(),
                'scheduledAt' => (new \DateTimeImmutable('+1 week'))->format(\DateTimeInterface::ATOM),
                'duration' => 60,
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);

        self::assertSame(201, $response->getStatusCode());

        return $response->toArray()['@id'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function history(HttpClientInterface $client, string $query = ''): array
    {
        $response = $client->request('GET', '/me/token-transactions'.($query !== '' ? '?'.$query : ''));
        self::assertSame(200, $response->getStatusCode());

        return $response->toArray()['member'];
    }

    // ========================================
    // ACCÈS
    // ========================================

    public function testRequiresAuthentication(): void
    {
        $response = static::createClient()->request('GET', '/me/token-transactions');

        self::assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // MOUVEMENTS
    // ========================================

    public function testSignupBonusIsRecordedOnRegistration(): void
    {
        $client = static::createClient();
        $email = $this->uniqueEmail('register-tokens');

        $client->request('POST', '/register', [
            'json' => [
                'email' => $email,
                'plainPassword' => 'password123',
                'firstName' => 'John',
                'lastName' => 'Doe',
            ],
            'headers' => ['Content-Type' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/auth', ['json' => ['email' => $email, 'password' => 'password123']]);
        self::assertResponseIsSuccessful();

        $history = $this->history($client);

        self::assertCount(1, $history);
        self::assertSame(TokenTransaction::TYPE_SIGNUP_BONUS, $history[0]['type']);
        self::assertSame(1, $history[0]['amount']);
        self::assertSame(1, $history[0]['balanceAfter']);
        self::assertNull($history[0]['session'] ?? null);
    }

    public function testBookingASessionRecordsADebit(): void
    {
        $sessionIri = $this->bookSession();

        $history = $this->history($this->studentClient);

        self::assertCount(2, $history);
        self::assertSame(TokenTransaction::TYPE_SESSION_BOOKED, $history[0]['type']);
        self::assertSame(-1, $history[0]['amount']);
        self::assertSame(0, $history[0]['balanceAfter']);
        self::assertSame($sessionIri, $history[0]['session']);
        self::assertSame('React', $history[0]['skillTitle']);
        self::assertSame(TokenTransaction::TYPE_SIGNUP_BONUS, $history[1]['type']);
    }

    public function testCancellingAPaidSessionRecordsARefund(): void
    {
        $sessionIri = $this->bookSession();

        $response = $this->requestUnsafe($this->studentClient, 'PATCH', $sessionIri.'/cancel', $this->studentCsrfToken, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $response->getStatusCode());

        $history = $this->history($this->studentClient);

        self::assertSame(
            [TokenTransaction::TYPE_SESSION_REFUND, TokenTransaction::TYPE_SESSION_BOOKED, TokenTransaction::TYPE_SIGNUP_BONUS],
            array_column($history, 'type'),
        );
        self::assertSame(1, $history[0]['amount']);
        self::assertSame(1, $history[0]['balanceAfter']);
        self::assertSame($sessionIri, $history[0]['session']);
    }

    public function testDeletingAPaidSessionKeepsTheRefundWithoutSession(): void
    {
        $sessionIri = $this->bookSession();

        $response = $this->requestUnsafe($this->studentClient, 'DELETE', $sessionIri, $this->studentCsrfToken);
        self::assertSame(204, $response->getStatusCode());

        $history = $this->history($this->studentClient);

        self::assertSame(
            [TokenTransaction::TYPE_SESSION_REFUND, TokenTransaction::TYPE_SESSION_BOOKED, TokenTransaction::TYPE_SIGNUP_BONUS],
            array_column($history, 'type'),
        );
        // La session supprimée n'est plus référencée, mais le mouvement reste dans l'historique.
        self::assertNull($history[0]['session'] ?? null);
        self::assertNull($history[1]['session'] ?? null);
    }

    public function testCompletingASessionCreditsTheMentor(): void
    {
        $session = SessionFactory::createOne([
            'mentor' => $this->mentor,
            'student' => $this->student,
            'skill' => $this->skill,
            'status' => Session::STATUS_CONFIRMED,
        ]);

        $response = $this->requestUnsafe($this->mentorClient, 'PATCH', '/sessions/'.$session->getId().'/complete', $this->mentorCsrfToken, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $response->getStatusCode());

        $history = $this->history($this->mentorClient);

        self::assertSame(TokenTransaction::TYPE_SESSION_GIVEN, $history[0]['type']);
        self::assertSame(1, $history[0]['amount']);
        self::assertSame(2, $history[0]['balanceAfter']);
        self::assertSame('/sessions/'.$session->getId(), $history[0]['session']);
    }

    public function testPerfectMatchBookingRecordsNothing(): void
    {
        // Le student enseigne ce que le mentor veut apprendre : session gratuite.
        $otherSkill = SkillFactory::createOne(['title' => 'Figma']);
        UserSkillFactory::createOne(['owner' => $this->student, 'skill' => $otherSkill, 'type' => UserSkill::TYPE_TEACH]);
        UserSkillFactory::createOne(['owner' => $this->mentor, 'skill' => $otherSkill, 'type' => UserSkill::TYPE_LEARN]);

        $this->bookSession();

        self::assertSame([TokenTransaction::TYPE_SIGNUP_BONUS], array_column($this->history($this->studentClient), 'type'));
    }

    // ========================================
    // LISTE
    // ========================================

    public function testUserOnlySeesOwnTransactions(): void
    {
        $this->bookSession();

        $history = $this->history($this->mentorClient);

        self::assertSame([TokenTransaction::TYPE_SIGNUP_BONUS], array_column($history, 'type'));
    }

    public function testFilterByType(): void
    {
        $this->bookSession();

        $history = $this->history($this->studentClient, 'type='.TokenTransaction::TYPE_SESSION_BOOKED);

        self::assertSame([TokenTransaction::TYPE_SESSION_BOOKED], array_column($history, 'type'));
    }

    public function testInvalidTypeReturns400(): void
    {
        $response = $this->studentClient->request('GET', '/me/token-transactions?type=unknown');

        self::assertSame(400, $response->getStatusCode());
    }

    public function testPagination(): void
    {
        $this->bookSession();

        $response = $this->studentClient->request('GET', '/me/token-transactions?itemsPerPage=1&page=2');
        $data = $response->toArray();

        self::assertSame(2, $data['totalItems']);
        self::assertSame([TokenTransaction::TYPE_SIGNUP_BONUS], array_column($data['member'], 'type'));
    }
}

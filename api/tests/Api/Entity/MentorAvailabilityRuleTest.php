<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\MentorAvailabilityRule;
use App\Factory\MentorAvailabilityRuleFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MentorAvailabilityRuleTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $mentorClient;
    private string $mentorCsrfToken;
    private $mentor;

    private HttpClientInterface $studentClient;
    private string $studentCsrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        [
            $this->mentorClient,
            $this->mentorCsrfToken,
            $this->mentor,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('mentor-availability'),
            password: 'password',
            extraData: ['isMentor' => true],
        );

        [
            $this->studentClient,
            $this->studentCsrfToken,
        ] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('student-availability'),
            password: 'password',
            extraData: ['isMentor' => false],
        );
    }

    public function testMentorCanCreateAvailabilityRule(): void
    {
        $response = $this->requestUnsafe(
            $this->mentorClient,
            'POST',
            '/mentor_availability_rules',
            $this->mentorCsrfToken,
            [
                'json' => [
                    'type' => MentorAvailabilityRule::TYPE_WEEKLY,
                    'dayOfWeek' => 1,
                    'startTime' => '1970-01-01T17:00:00+00:00',
                    'endTime' => '1970-01-01T18:00:00+00:00',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertSame('weekly', $data['type'] ?? null);
        self::assertSame('/users/'.$this->mentor->getId(), $data['mentor'] ?? null);
    }

    public function testNonMentorCannotCreateAvailabilityRule(): void
    {
        $response = $this->requestUnsafe(
            $this->studentClient,
            'POST',
            '/mentor_availability_rules',
            $this->studentCsrfToken,
            [
                'json' => [
                    'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
                    'startsAt' => (new \DateTimeImmutable('+1 day'))->format(\DateTimeInterface::ATOM),
                    'endsAt' => (new \DateTimeImmutable('+1 day +1 hour'))->format(\DateTimeInterface::ATOM),
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertSame('Only mentors can create availability rules', $data['violations'][0]['message'] ?? null);
    }

    public function testCanGetMentorAvailableSlots(): void
    {
        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
            'startsAt' => new \DateTimeImmutable('+1 day 17:00'),
            'endsAt' => new \DateTimeImmutable('+1 day 19:00'),
        ]);

        $from = (new \DateTimeImmutable('+1 day 16:00'))->format(\DateTimeInterface::ATOM);
        $to = (new \DateTimeImmutable('+1 day 20:00'))->format(\DateTimeInterface::ATOM);

        $response = $this->mentorClient->request('GET', sprintf('/mentors/%d/available-slots?from=%s&to=%s&duration=60', $this->mentor->getId(), urlencode($from), urlencode($to)));

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertNotEmpty($data['member'] ?? []);
    }
}

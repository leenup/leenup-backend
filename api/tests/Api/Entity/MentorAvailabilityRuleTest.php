<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\MentorAvailabilityRule;
use App\Entity\User;
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
    private User $student;

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
            $this->student,
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

    public function testExclusionRuleRemovesMatchingSlots(): void
    {
        $startsAt = new \DateTimeImmutable('+2 days 17:00');
        $endsAt = $startsAt->modify('+2 hours');

        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
        ]);

        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => MentorAvailabilityRule::TYPE_EXCLUSION,
            'startsAt' => $startsAt->modify('+1 hour'),
            'endsAt' => $startsAt->modify('+2 hours'),
        ]);

        $from = $startsAt->modify('-30 minutes')->format(\DateTimeInterface::ATOM);
        $to = $endsAt->modify('+30 minutes')->format(\DateTimeInterface::ATOM);

        $response = $this->mentorClient->request('GET', sprintf('/mentors/%d/available-slots?from=%s&to=%s&duration=60', $this->mentor->getId(), urlencode($from), urlencode($to)));

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);

        $starts = array_map(static fn (array $slot): string => $slot['startAt'] ?? '', $data['member'] ?? []);
        self::assertContains($startsAt->format(\DateTimeInterface::ATOM), $starts);
        self::assertNotContains($startsAt->modify('+1 hour')->format(\DateTimeInterface::ATOM), $starts);
    }

    public function testGetAvailableSlotsReturnsEmptyForNonMentor(): void
    {
        $response = $this->studentClient->request('GET', sprintf('/mentors/%d/available-slots', $this->student->getId()));

        self::assertSame(200, $response->getStatusCode());
        $data = $response->toArray(false);
        self::assertSame(0, $data['totalItems'] ?? 0);
    }
}

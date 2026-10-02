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

    public function testCannotCreateRuleWithInvalidTimezone(): void
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
                    'timezone' => 'Mars/Olympus_Mons',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
    }

    // =====================================================
    // CALCUL DES CRÉNEAUX
    // =====================================================

    public function testWeeklyRuleIsEvaluatedInRuleTimezone(): void
    {
        $this->createWeeklyRule(MentorAvailabilityRule::TYPE_WEEKLY, 1, '17:00', '20:00');

        $monday = $this->nextMondayInParis();

        // Requête en UTC : les créneaux doivent rester 17:00–20:00 heure de Paris.
        $starts = $this->fetchSlotStarts($monday, $monday->modify('+1 day'), 60);

        self::assertSame(
            $this->parisTimestamps($monday, ['17:00', '17:30', '18:00', '18:30', '19:00']),
            $starts
        );
    }

    public function testWeeklyRuleCanSpanMidnight(): void
    {
        $this->createWeeklyRule(MentorAvailabilityRule::TYPE_WEEKLY, 1, '22:00', '02:00');

        $monday = $this->nextMondayInParis();
        $starts = $this->fetchSlotStarts($monday->setTime(20, 0), $monday->modify('+1 day')->setTime(4, 0), 60);

        $tuesday = $monday->modify('+1 day');
        self::assertSame(
            array_merge(
                $this->parisTimestamps($monday, ['22:00', '22:30', '23:00', '23:30']),
                $this->parisTimestamps($tuesday, ['00:00', '00:30', '01:00'])
            ),
            $starts
        );
    }

    public function testExclusionBlocksPartiallyOverlappingSlots(): void
    {
        $this->createWeeklyRule(MentorAvailabilityRule::TYPE_WEEKLY, 1, '17:00', '20:00');
        $this->createWeeklyRule(MentorAvailabilityRule::TYPE_EXCLUSION, 1, '18:00', '19:00');

        $monday = $this->nextMondayInParis();
        $starts = $this->fetchSlotStarts($monday, $monday->modify('+1 day'), 60);

        // 17:30–18:30 et 18:30–19:30 chevauchent l'exclusion : ils doivent disparaître.
        self::assertSame($this->parisTimestamps($monday, ['17:00', '19:00']), $starts);
    }

    public function testAvailableSlotsAreNeverInThePast(): void
    {
        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => MentorAvailabilityRule::TYPE_ONE_SHOT,
            'startsAt' => new \DateTimeImmutable('-2 days'),
            'endsAt' => new \DateTimeImmutable('+1 day'),
        ]);

        $now = time();
        $starts = $this->fetchSlotStarts(new \DateTimeImmutable('-1 day'), new \DateTimeImmutable('+1 day'), 60);

        self::assertNotEmpty($starts);
        self::assertGreaterThanOrEqual($now, min($starts));
        self::assertSame(0, min($starts) % 1800, 'Slots should start on a 30-minute boundary.');
    }

    /**
     * @dataProvider invalidSlotQueryProvider
     */
    public function testInvalidSlotQueryReturns400(array $query): void
    {
        $response = $this->studentClient->request(
            'GET',
            sprintf('/mentors/%d/available-slots?%s', $this->mentor->getId(), http_build_query($query))
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public static function invalidSlotQueryProvider(): iterable
    {
        yield 'range too large' => [['from' => '+1 day', 'to' => '+60 days']];
        yield 'invalid date' => [['from' => 'not-a-date']];
        yield 'zero duration' => [['duration' => '0']];
        yield 'non numeric duration' => [['duration' => 'abc']];
        yield 'duration too long' => [['duration' => '2000']];
    }

    private function createWeeklyRule(string $type, int $dayOfWeek, string $startTime, string $endTime): void
    {
        MentorAvailabilityRuleFactory::createOne([
            'mentor' => $this->mentor,
            'type' => $type,
            'dayOfWeek' => $dayOfWeek,
            'startTime' => new \DateTimeImmutable('1970-01-01 '.$startTime),
            'endTime' => new \DateTimeImmutable('1970-01-01 '.$endTime),
            'startsAt' => null,
            'endsAt' => null,
            'timezone' => 'Europe/Paris',
        ]);
    }

    private function nextMondayInParis(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('next monday', new \DateTimeZone('Europe/Paris'));
    }

    /**
     * @return int[] timestamps des débuts de créneaux, requête envoyée en UTC
     */
    private function fetchSlotStarts(\DateTimeImmutable $from, \DateTimeImmutable $to, int $duration): array
    {
        $utc = new \DateTimeZone('UTC');
        $response = $this->studentClient->request('GET', sprintf(
            '/mentors/%d/available-slots?%s',
            $this->mentor->getId(),
            http_build_query([
                'from' => $from->setTimezone($utc)->format(\DateTimeInterface::ATOM),
                'to' => $to->setTimezone($utc)->format(\DateTimeInterface::ATOM),
                'duration' => $duration,
            ])
        ));

        self::assertSame(200, $response->getStatusCode());

        return array_map(
            static fn (array $slot): int => (new \DateTimeImmutable($slot['startAt']))->getTimestamp(),
            $response->toArray(false)['member'] ?? []
        );
    }

    /**
     * @param string[] $times heures "HH:MM" à Paris
     *
     * @return int[]
     */
    private function parisTimestamps(\DateTimeImmutable $day, array $times): array
    {
        return array_map(static function (string $time) use ($day): int {
            [$hour, $minute] = array_map('intval', explode(':', $time));

            return $day->setTime($hour, $minute)->getTimestamp();
        }, $times);
    }
}

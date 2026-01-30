<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\MentorAvailability;
use App\Entity\User;
use App\Factory\MentorAvailabilityFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MyMentorAvailabilityTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private User $user;
    private User $anotherUser;

    private HttpClientInterface $userClient;
    private HttpClientInterface $anotherUserClient;

    private string $userCsrfToken;
    private string $anotherUserCsrfToken;

    private MentorAvailability $availabilityMonday;
    private MentorAvailability $availabilityTuesday;
    private MentorAvailability $anotherUserAvailability;

    protected function setUp(): void
    {
        parent::setUp();

        [
            $this->userClient,
            $this->userCsrfToken,
            $this->user,
        ] = $this->createAuthenticatedUser(
            email: 'availability@example.com',
            password: 'password',
        );

        [
            $this->anotherUserClient,
            $this->anotherUserCsrfToken,
            $this->anotherUser,
        ] = $this->createAuthenticatedUser(
            email: 'availability2@example.com',
            password: 'password',
        );

        $this->availabilityMonday = MentorAvailabilityFactory::createOne([
            'mentor' => $this->user,
            'dayOfWeek' => 1,
            'startTime' => new \DateTimeImmutable('09:00'),
            'endTime' => new \DateTimeImmutable('12:00'),
        ]);

        $this->availabilityTuesday = MentorAvailabilityFactory::createOne([
            'mentor' => $this->user,
            'dayOfWeek' => 2,
            'startTime' => new \DateTimeImmutable('14:00'),
            'endTime' => new \DateTimeImmutable('18:00'),
        ]);

        $this->anotherUserAvailability = MentorAvailabilityFactory::createOne([
            'mentor' => $this->anotherUser,
            'dayOfWeek' => 3,
            'startTime' => new \DateTimeImmutable('10:00'),
            'endTime' => new \DateTimeImmutable('16:00'),
        ]);
    }

    public function testGetMyAvailability(): void
    {
        $response = $this->userClient->request('GET', '/me/availability');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('/contexts/MyMentorAvailability', $data['@context']);
        self::assertSame('Collection', $data['@type']);
        self::assertSame(2, $data['totalItems']);
        self::assertCount(2, $data['member']);
    }

    public function testGetMyAvailabilityStructure(): void
    {
        $response = $this->userClient->request('GET', '/me/availability');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        $item = $data['member'][0] ?? null;
        self::assertNotNull($item);

        self::assertSame('MyMentorAvailability', $item['@type']);
        self::assertArrayHasKey('id', $item);
        self::assertArrayHasKey('dayOfWeek', $item);
        self::assertArrayHasKey('startTime', $item);
        self::assertArrayHasKey('endTime', $item);
        self::assertArrayHasKey('createdAt', $item);
    }

    public function testGetMyAvailabilityWithoutAuth(): void
    {
        $response = static::createClient()->request('GET', '/me/availability');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testGetMyAvailabilityById(): void
    {
        $availabilityId = $this->availabilityMonday->getId();

        $response = $this->userClient->request('GET', '/me/availability/'.$availabilityId);

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame($availabilityId, $data['id']);
        self::assertSame(1, $data['dayOfWeek']);
    }

    public function testGetAnotherUserAvailabilityByIdFails(): void
    {
        $response = $this->userClient->request(
            'GET',
            '/me/availability/'.$this->anotherUserAvailability->getId()
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPostAvailabilitySuccessfully(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/availability',
            $this->userCsrfToken,
            [
                'json' => [
                    'dayOfWeek' => 4,
                    'startTime' => '08:00:00',
                    'endTime' => '11:00:00',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(4, $data['dayOfWeek']);
    }

    public function testPostAvailabilityWithInvalidDayFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/availability',
            $this->userCsrfToken,
            [
                'json' => [
                    'dayOfWeek' => 7,
                    'startTime' => '08:00:00',
                    'endTime' => '11:00:00',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchAvailability(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'PATCH',
            '/me/availability/'.$this->availabilityMonday->getId(),
            $this->userCsrfToken,
            [
                'json' => [
                    'startTime' => '10:00:00',
                    'endTime' => '13:00:00',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('10:00:00', $data['startTime']);
        self::assertSame('13:00:00', $data['endTime']);
    }

    public function testDeleteAvailability(): void
    {
        $availabilityId = $this->availabilityTuesday->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/availability/'.$availabilityId,
            $this->userCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $responseGet = $this->userClient->request('GET', '/me/availability/'.$availabilityId);
        self::assertSame(404, $responseGet->getStatusCode());
    }

    public function testDeleteAnotherUserAvailabilityFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/availability/'.$this->anotherUserAvailability->getId(),
            $this->userCsrfToken
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testDeleteAvailabilityWithoutAuth(): void
    {
        $response = static::createClient()->request(
            'DELETE',
            '/me/availability/'.$this->availabilityMonday->getId()
        );

        self::assertSame(401, $response->getStatusCode());
    }
}

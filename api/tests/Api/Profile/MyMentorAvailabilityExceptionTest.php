<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\MentorAvailabilityException;
use App\Entity\User;
use App\Factory\MentorAvailabilityExceptionFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MyMentorAvailabilityExceptionTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private User $user;
    private User $anotherUser;

    private HttpClientInterface $userClient;
    private HttpClientInterface $anotherUserClient;

    private string $userCsrfToken;
    private string $anotherUserCsrfToken;

    private MentorAvailabilityException $userException;
    private MentorAvailabilityException $anotherUserException;

    protected function setUp(): void
    {
        parent::setUp();

        [
            $this->userClient,
            $this->userCsrfToken,
            $this->user,
        ] = $this->createAuthenticatedUser(
            email: 'exceptions@example.com',
            password: 'password',
        );

        [
            $this->anotherUserClient,
            $this->anotherUserCsrfToken,
            $this->anotherUser,
        ] = $this->createAuthenticatedUser(
            email: 'exceptions2@example.com',
            password: 'password',
        );

        $this->userException = MentorAvailabilityExceptionFactory::createOne([
            'mentor' => $this->user,
            'date' => new \DateTimeImmutable('2025-01-10'),
            'startTime' => new \DateTimeImmutable('08:00'),
            'endTime' => new \DateTimeImmutable('12:00'),
            'type' => MentorAvailabilityException::TYPE_UNAVAILABLE,
        ]);

        $this->anotherUserException = MentorAvailabilityExceptionFactory::createOne([
            'mentor' => $this->anotherUser,
            'date' => new \DateTimeImmutable('2025-01-12'),
            'startTime' => new \DateTimeImmutable('10:00'),
            'endTime' => new \DateTimeImmutable('14:00'),
            'type' => MentorAvailabilityException::TYPE_OVERRIDE,
        ]);
    }

    public function testGetMyAvailabilityExceptions(): void
    {
        $response = $this->userClient->request('GET', '/me/availability-exceptions');

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);

        self::assertSame('/contexts/MyMentorAvailabilityException', $data['@context']);
        self::assertSame(1, $data['totalItems']);
        self::assertCount(1, $data['member']);
    }

    public function testGetMyAvailabilityExceptionById(): void
    {
        $exceptionId = $this->userException->getId();

        $response = $this->userClient->request('GET', '/me/availability-exceptions/'.$exceptionId);

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame($exceptionId, $data['id']);
        self::assertSame(MentorAvailabilityException::TYPE_UNAVAILABLE, $data['type']);
    }

    public function testGetAnotherUserAvailabilityExceptionFails(): void
    {
        $response = $this->userClient->request(
            'GET',
            '/me/availability-exceptions/'.$this->anotherUserException->getId()
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testPostAvailabilityExceptionSuccessfully(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/availability-exceptions',
            $this->userCsrfToken,
            [
                'json' => [
                    'date' => '2025-02-01',
                    'startTime' => '09:00:00',
                    'endTime' => '11:00:00',
                    'type' => MentorAvailabilityException::TYPE_OVERRIDE,
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(MentorAvailabilityException::TYPE_OVERRIDE, $data['type']);
    }

    public function testPostAvailabilityExceptionWithInvalidTypeFails(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/me/availability-exceptions',
            $this->userCsrfToken,
            [
                'json' => [
                    'date' => '2025-02-02',
                    'type' => 'invalid',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testPatchAvailabilityException(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'PATCH',
            '/me/availability-exceptions/'.$this->userException->getId(),
            $this->userCsrfToken,
            [
                'json' => [
                    'type' => MentorAvailabilityException::TYPE_OVERRIDE,
                    'startTime' => '13:00:00',
                    'endTime' => '16:00:00',
                ],
                'headers' => ['Content-Type' => 'application/ld+json'],
            ]
        );

        self::assertSame(200, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame(MentorAvailabilityException::TYPE_OVERRIDE, $data['type']);
    }

    public function testDeleteAvailabilityException(): void
    {
        $exceptionId = $this->userException->getId();

        $response = $this->requestUnsafe(
            $this->userClient,
            'DELETE',
            '/me/availability-exceptions/'.$exceptionId,
            $this->userCsrfToken
        );

        self::assertSame(204, $response->getStatusCode());

        $responseGet = $this->userClient->request('GET', '/me/availability-exceptions/'.$exceptionId);
        self::assertSame(404, $responseGet->getStatusCode());
    }

    public function testDeleteAvailabilityExceptionWithoutAuth(): void
    {
        $response = static::createClient()->request(
            'DELETE',
            '/me/availability-exceptions/'.$this->userException->getId()
        );

        self::assertSame(401, $response->getStatusCode());
    }
}

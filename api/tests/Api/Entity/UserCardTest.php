<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\UserCard;
use App\Factory\CardFactory;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Zenstruck\Foundry\Test\Factories;

class UserCardTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    public function testUserCannotGrantCardToAnotherUser(): void
    {
        $victim = UserFactory::createOne();
        $card = CardFactory::createOne(['isActive' => true]);

        [$client, $csrfToken] = $this->createAuthenticatedUser($this->uniqueEmail('user-card'), 'password');

        $response = $this->requestUnsafe($client, 'POST', '/user_cards', $csrfToken, [
            'json' => [
                'user' => '/users/'.$victim->getId(),
                'card' => '/cards/'.$card->getId(),
                'obtainedAt' => '2026-01-01T00:00:00+00:00',
            ],
        ]);

        self::assertSame(403, $response->getStatusCode());

        $em = static::getContainer()->get('doctrine')->getManager();
        self::assertSame(0, $em->getRepository(UserCard::class)->count(['user' => $victim->getId()]));
    }

    public function testUserCannotListUserCards(): void
    {
        [$client] = $this->createAuthenticatedUser($this->uniqueEmail('user-card'), 'password');

        $response = $client->request('GET', '/user_cards');

        self::assertSame(403, $response->getStatusCode());
    }

    public function testAdminCanListUserCards(): void
    {
        [$client] = $this->createAuthenticatedAdmin($this->uniqueEmail('admin-user-card'), 'password');

        $response = $client->request('GET', '/user_cards');

        self::assertSame(200, $response->getStatusCode());
    }
}

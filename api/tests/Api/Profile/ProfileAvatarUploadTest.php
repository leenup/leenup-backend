<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\User;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class ProfileAvatarUploadTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private string $csrfToken;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->client, $this->csrfToken, $this->user] = $this->createAuthenticatedUser(
            'avatar-test@example.com',
            'password'
        );
    }

    public function testUploadProfileAvatar(): void
    {
        $uploadedFile = $this->createUploadedPng();

        $response = $this->client->request('POST', '/me/avatar', [
            'headers' => ['X-CSRF-TOKEN' => $this->csrfToken],
            'extra' => [
                'files' => ['file' => $uploadedFile],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray(false);
        $this->assertArrayHasKey('avatarUrl', $data);
        $this->assertStringContainsString('/upload/profile/', $data['avatarUrl']);

        $em = self::getContainer()->get('doctrine')->getManager();
        $em->refresh($this->user);
        $this->assertSame($data['avatarUrl'], $this->user->getAvatarUrl());
    }

    public function testUploadProfileAvatarRequiresAuthentication(): void
    {
        $uploadedFile = $this->createUploadedPng();

        static::createClient()->request('POST', '/me/avatar', [
            'extra' => [
                'files' => ['file' => $uploadedFile],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadProfileAvatarRejectsInvalidMimeType(): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'avatar-invalid-');
        file_put_contents($tmpPath, 'not-an-image');

        $uploadedFile = new UploadedFile(
            $tmpPath,
            'avatar.txt',
            'text/plain',
            null,
            true
        );

        $this->client->request('POST', '/me/avatar', [
            'headers' => ['X-CSRF-TOKEN' => $this->csrfToken],
            'extra' => [
                'files' => ['file' => $uploadedFile],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    private function createUploadedPng(): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'avatar-');

        file_put_contents(
            $tmpPath,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9W3tYAAAAASUVORK5CYII=')
        );

        return new UploadedFile(
            $tmpPath,
            'avatar.png',
            'image/png',
            null,
            true
        );
    }
}

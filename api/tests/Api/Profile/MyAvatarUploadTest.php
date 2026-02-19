<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MyAvatarUploadTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->client, $this->csrfToken] = $this->createAuthenticatedUser(
            $this->uniqueEmail('avatar'),
            'password'
        );
    }

    public function testUploadMyAvatar(): void
    {
        $filePath = $this->createTinyPng();
        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', null, true);

        $response = $this->requestUnsafe($this->client, 'POST', '/me/avatar', $this->csrfToken, [
            'extra' => [
                'files' => [
                    'avatar' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray(false);
        $this->assertArrayHasKey('avatarUrl', $data);
        $this->assertStringContainsString('/uploads/leenup/avatars/user-', $data['avatarUrl']);
    }

    public function testUploadMyAvatarRejectsInvalidMimeType(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'avatar_txt_');
        file_put_contents($filePath, 'not-an-image');
        $uploadedFile = new UploadedFile($filePath, 'avatar.txt', 'text/plain', null, true);

        $this->requestUnsafe($this->client, 'POST', '/me/avatar', $this->csrfToken, [
            'extra' => [
                'files' => [
                    'avatar' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testUploadMyAvatarRequiresAuthentication(): void
    {
        $filePath = $this->createTinyPng();
        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', null, true);

        static::createClient()->request('POST', '/me/avatar', [
            'extra' => [
                'files' => [
                    'avatar' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    private function createTinyPng(): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'avatar_png_');

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9oNn14kAAAAASUVORK5CYII=');
        file_put_contents($filePath, $png);

        return $filePath;
    }
}

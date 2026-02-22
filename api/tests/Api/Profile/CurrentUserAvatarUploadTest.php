<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class CurrentUserAvatarUploadTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->client, $this->csrfToken] = $this->createAuthenticatedUser(
            $this->uniqueEmail('avatar-test'),
            'password'
        );
    }

    public function testUploadAvatarForCurrentUser(): void
    {
        $uploadedFile = $this->createTestImageUpload();

        $response = $this->requestUnsafe($this->client, 'POST', '/me/avatar', $this->csrfToken, [
                        'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $payload = $response->toArray(false);
        $this->assertArrayHasKey('avatarUrl', $payload);
        $this->assertStringStartsWith('/upload/profile/', $payload['avatarUrl']);
        $this->assertFileExists(sprintf('%s/public%s', dirname(__DIR__, 4), $payload['avatarUrl']));
    }

    public function testUploadAvatarRequiresAuthentication(): void
    {
        $uploadedFile = $this->createTestImageUpload();

        static::createClient()->request('POST', '/me/avatar', [
                        'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadAvatarRejectsInvalidFileType(): void
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

        $this->requestUnsafe($this->client, 'POST', '/me/avatar', $this->csrfToken, [
                        'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    private function createTestImageUpload(): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'avatar-');

        // 1x1 PNG
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wn2R3wAAAAASUVORK5CYII=');
        file_put_contents($tmpPath, $pngData);

        return new UploadedFile(
            $tmpPath,
            'avatar.png',
            'image/png',
            null,
            true
        );
    }
}

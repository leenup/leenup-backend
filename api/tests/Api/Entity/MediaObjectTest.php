<?php

namespace App\Tests\Api\Entity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Enum\UploadDirectoryEnum;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

class MediaObjectTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $client;
    private string $csrfToken;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->client, $this->csrfToken] = $this->createAuthenticatedUser(
            $this->uniqueEmail('media-upload'),
            'password'
        );
    }

    public function testUploadProfileMediaObject(): void
    {
        $filePath = tempnam(sys_get_temp_dir(), 'avatar_test_');
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7ZxWQAAAAASUVORK5CYII='));

        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', null, true);

        $response = $this->requestUnsafe($this->client, 'POST', '/media_objects', $this->csrfToken, [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => [
                    'directory' => UploadDirectoryEnum::PROFILE->value,
                ],
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);

        $data = $response->toArray(false);
        $this->assertArrayHasKey('contentUrl', $data);
        $this->assertStringStartsWith('/upload/profile/', $data['contentUrl']);

        @unlink($filePath);
    }
}

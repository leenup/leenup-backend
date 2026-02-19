<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;

class ProfileAvatarUploadTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    public function testUploadAvatarSuccess(): void
    {
        [$client, $csrfToken, $user] = $this->createAuthenticatedUser('avatar-upload@example.com', 'password');

        $filePath = tempnam(sys_get_temp_dir(), 'avatar_');
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9W7tsAAAAASUVORK5CYII='));

        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', null, true);

        $response = $this->requestUnsafe($client, 'POST', '/me/avatar', $csrfToken, [
            'extra' => [
                'files' => ['file' => $uploadedFile],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('avatarUrl', $data);
        $this->assertStringContainsString('/upload/profile/', $data['avatarUrl']);

        self::getContainer()->get('doctrine')->getManager()->refresh($user);
        $this->assertSame($data['avatarUrl'], $user->getAvatarUrl());
    }

    public function testUploadAvatarRequiresAuthentication(): void
    {
        $client = static::createClient();

        $filePath = tempnam(sys_get_temp_dir(), 'avatar_');
        file_put_contents($filePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO9W7tsAAAAASUVORK5CYII='));

        $uploadedFile = new UploadedFile($filePath, 'avatar.png', 'image/png', null, true);

        $client->request('POST', '/me/avatar', [
            'extra' => [
                'files' => ['file' => $uploadedFile],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testUploadAvatarRequiresFile(): void
    {
        [$client, $csrfToken] = $this->createAuthenticatedUser('avatar-upload-required@example.com', 'password');

        $this->requestUnsafe($client, 'POST', '/me/avatar', $csrfToken);

        $this->assertResponseStatusCodeSame(422);
        $data = $this->getJsonFromResponse();
        $this->assertArrayHasKey('violations', $data);
        $this->assertNotEmpty($data['violations']);
    }
}


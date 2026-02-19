<?php

namespace App\Tests\Api\Profile;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Factory\UserFactory;
use App\Tests\Api\Trait\AuthenticatedApiTestTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Zenstruck\Foundry\Test\Factories;

final class ProfileImageUploadTest extends ApiTestCase
{
    use Factories;
    use AuthenticatedApiTestTrait;

    private HttpClientInterface $userClient;

    private string $csrfToken;

    private \App\Entity\User $authenticatedUser;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->userClient, $this->csrfToken, $this->authenticatedUser] = $this->createAuthenticatedUser(
            email: $this->uniqueEmail('upload-user'),
            password: 'user123'
        );
    }

    public function testUploadProfileImageForCurrentUser(): void
    {
        $uploadedFile = $this->createTestImageUpload();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            sprintf('/users/%d/profile-image', $this->authenticatedUser->getId()),
            $this->csrfToken,
            [
                'extra' => [
                    'files' => [
                        'file' => $uploadedFile,
                    ],
                ],
            ]
        );

        self::assertContains($response->getStatusCode(), [200, 201]);

        $data = $response->toArray(false);
        self::assertArrayHasKey('avatarUrl', $data);
        self::assertNotNull($data['avatarUrl']);
        self::assertStringContainsString('/upload/profile/', $data['avatarUrl']);

        $this->assertUploadedFileExistsFromUrl($data['avatarUrl']);
    }

    public function testUploadProfileImageForbiddenForAnotherUser(): void
    {
        $targetUser = UserFactory::createOne([
            'email' => $this->uniqueEmail('target-user'),
            'plainPassword' => 'user123',
        ]);

        $uploadedFile = $this->createTestImageUpload();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            sprintf('/users/%d/profile-image', $targetUser->getId()),
            $this->csrfToken,
            [
                'extra' => [
                    'files' => [
                        'file' => $uploadedFile,
                    ],
                ],
            ]
        );

        self::assertSame(403, $response->getStatusCode());
    }

    public function testUploadProfileImageWithoutFileReturnsBadRequest(): void
    {
        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            sprintf('/users/%d/profile-image', $this->authenticatedUser->getId()),
            $this->csrfToken,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testGenericUploadsEndpointStoresProfileFile(): void
    {
        $uploadedFile = $this->createTestImageUpload();

        $response = $this->requestUnsafe(
            $this->userClient,
            'POST',
            '/uploads/profile',
            $this->csrfToken,
            [
                'extra' => [
                    'files' => [
                        'file' => $uploadedFile,
                    ],
                ],
            ]
        );

        self::assertSame(201, $response->getStatusCode());

        $data = $response->toArray(false);
        self::assertSame('profile', $data['type']);
        self::assertStringStartsWith('/upload/profile/', $data['path']);

        $absolutePath = sprintf('%s/public%s', self::getContainer()->getParameter('kernel.project_dir'), $data['path']);
        self::assertFileExists($absolutePath);
    }

    public function testGenericUploadsEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();

        $uploadedFile = $this->createTestImageUpload();

        $response = $client->request('POST', '/uploads/profile', [
            'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    private function createTestImageUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'profile_upload_');
        self::assertNotFalse($path);

        file_put_contents($path, 'fake-image-content');

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', null, true);
    }

    private function assertUploadedFileExistsFromUrl(string $avatarUrl): void
    {
        $parsedPath = parse_url($avatarUrl, PHP_URL_PATH);
        self::assertIsString($parsedPath);

        $absolutePath = sprintf('%s/public%s', self::getContainer()->getParameter('kernel.project_dir'), $parsedPath);
        self::assertFileExists($absolutePath);
    }
}

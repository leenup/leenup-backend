<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class FileUploader
{
    private const UPLOAD_DIRECTORIES = [
        'profile' => 'upload/profile',
        'document' => 'upload/document',
        'other' => 'upload/other',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @return array{filename: string, path: string, type: string}
     */
    public function upload(UploadedFile $file, string $type): array
    {
        $normalizedType = strtolower(trim($type));
        $relativeDirectory = self::UPLOAD_DIRECTORIES[$normalizedType] ?? self::UPLOAD_DIRECTORIES['other'];
        $targetDirectory = sprintf('%s/%s', rtrim($this->publicDir, '/'), $relativeDirectory);

        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $this->slugger->slug($originalFilename);
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s-%s.%s', $safeFilename, bin2hex(random_bytes(8)), $extension);

        $file->move($targetDirectory, $filename);

        return [
            'filename' => $filename,
            'path' => sprintf('/%s/%s', $relativeDirectory, $filename),
            'type' => $normalizedType,
        ];
    }
}

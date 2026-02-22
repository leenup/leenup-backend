<?php

namespace App\Service;

use App\Enum\UploadDirectory;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class FileUploader
{
    public function __construct(
        private string $uploadBaseDirectory,
        private SluggerInterface $slugger,
        private Filesystem $filesystem,
    ) {
    }

    public function upload(UploadedFile $file, UploadDirectory $directory): string
    {
        $this->filesystem->mkdir(sprintf('%s/%s', $this->uploadBaseDirectory, $directory->value));

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename)->lower();
        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';

        $filename = sprintf('%s-%s.%s', $safeFilename, bin2hex(random_bytes(6)), $extension);

        $file->move(sprintf('%s/%s', $this->uploadBaseDirectory, $directory->value), $filename);

        return sprintf('/upload/%s/%s', $directory->value, $filename);
    }
}

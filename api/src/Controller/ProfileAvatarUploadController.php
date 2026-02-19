<?php

namespace App\Controller;

use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Profile\ProfileAvatarUpload;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
final class ProfileAvatarUploadController
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ValidatorInterface $validator,
        private readonly string $projectDir,
    ) {
    }

    public function __invoke(Request $request): ProfileAvatarUpload
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new HttpException(Response::HTTP_UNAUTHORIZED, 'Authentication required.');
        }

        $file = $request->files->get('file');
        $violations = $this->validator->validate($file, [
            new Assert\NotNull(message: 'The "file" field is required.'),
            new Assert\Image(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                mimeTypesMessage: 'Please upload a valid image file (jpeg, png, webp, gif).',
            ),
        ]);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $uploadDirectory = $this->projectDir.'/public/upload/profile';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Unable to create upload directory.');
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $this->slugger->slug($originalName)->lower()->toString();
        $fileName = sprintf('%s-%s.%s', $safeName ?: 'profile', uniqid('', true), $file->guessExtension() ?: 'bin');
        $file->move($uploadDirectory, $fileName);

        $avatarUrl = sprintf('%s/upload/profile/%s', $request->getSchemeAndHttpHost(), $fileName);

        $user->setAvatarUrl($avatarUrl);
        $this->entityManager->flush();

        $output = new ProfileAvatarUpload();
        $output->avatarUrl = $avatarUrl;

        return $output;
    }
}

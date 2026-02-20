<?php

namespace App\Controller\Profile;

use ApiPlatform\Validator\Exception\ValidationException;
use App\ApiResource\Profile\ProfileAvatarUpload;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class UploadProfileAvatarAction
{
    public function __construct(
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $uploadProfileDir,
        private readonly string $uploadProfilePublicPath,
    ) {
    }

    public function __invoke(Request $request): ProfileAvatarUpload
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \LogicException('User not authenticated');
        }

        $file = $request->files->get('file');

        $violations = $this->validator->validate($file, [
            new Assert\NotNull(message: 'A file is required.'),
            new Assert\File(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                mimeTypesMessage: 'Only JPEG, PNG, WEBP and GIF files are allowed.'
            ),
        ]);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        if (!$file instanceof UploadedFile) {
            throw new ValidationException(new ConstraintViolationList());
        }

        if (!is_dir($this->uploadProfileDir)) {
            mkdir($this->uploadProfileDir, 0775, true);
        }

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);

        $file->move($this->uploadProfileDir, $filename);

        $avatarUrl = sprintf(
            '%s%s/%s',
            $request->getSchemeAndHttpHost(),
            rtrim($this->uploadProfilePublicPath, '/'),
            $filename
        );

        $user->setAvatarUrl($avatarUrl);
        $user->onPreUpdate();
        $this->entityManager->flush();

        $output = new ProfileAvatarUpload();
        $output->avatarUrl = $avatarUrl;

        return $output;
    }
}

<?php

namespace App\Service\Upload;

use App\Entity\MediaObject;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

final class UploadDirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName(object|array $object, ?string $mappingName = null): string
    {
        if (!$object instanceof MediaObject) {
            throw new \InvalidArgumentException('UploadDirectoryNamer only supports MediaObject instances.');
        }

        return $object->getDirectory()->value;
    }
}

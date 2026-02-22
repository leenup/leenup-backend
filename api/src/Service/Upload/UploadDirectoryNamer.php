<?php

namespace App\Service\Upload;

use App\Entity\MediaObject;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\DirectoryNamerInterface;

final class UploadDirectoryNamer implements DirectoryNamerInterface
{
    public function directoryName(object|array $object, PropertyMapping $mapping): string
    {
        if (!$object instanceof MediaObject) {
            throw new \InvalidArgumentException('UploadDirectoryNamer only supports MediaObject instances.');
        }

        return $object->getDirectory()->value;
    }
}

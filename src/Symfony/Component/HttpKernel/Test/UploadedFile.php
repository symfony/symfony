<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Test;

use Symfony\Component\HttpFoundation\File\UploadedFile as BaseUploadedFile;

/**
 * An uploaded file class the avoids PHP's native uploaded file checks.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class UploadedFile extends BaseUploadedFile
{
    /**
     * Creates a new file with values from another.
     *
     * @param BaseUploadedFile $file An uploaded file
     *
     * @return UploadedFile A new uploaded file
     */
    static public function wrap(BaseUploadedFile $file)
    {
        return new static($file->getPath(), $file->getName(), $file->getMimeType(), $file->size(), $file->getError());
    }

    /**
     * Assumes this isn't actually an uploaded file and moves it anyway.
     */
    protected function moveUploadedFile($newPath)
    {
        return rename($this->getPath(), $newPath);
    }
}

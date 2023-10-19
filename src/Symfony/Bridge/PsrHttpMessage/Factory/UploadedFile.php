<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Factory;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile as BaseUploadedFile;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class UploadedFile extends BaseUploadedFile
{
    private bool $test = false;

    public function __construct(
        private readonly UploadedFileInterface $psrUploadedFile,
        callable $getTemporaryPath,
    ) {
        $error = $psrUploadedFile->getError();
        $path = '';

        if (\UPLOAD_ERR_NO_FILE !== $error) {
            $path = $psrUploadedFile->getStream()->getMetadata('uri') ?? '';

            if ($this->test = !\is_string($path) || !is_uploaded_file($path)) {
                $path = $getTemporaryPath();
                $psrUploadedFile->moveTo($path);
            }
        }

        parent::__construct(
            $path,
            (string) $psrUploadedFile->getClientFilename(),
            $psrUploadedFile->getClientMediaType(),
            $psrUploadedFile->getError(),
            $this->test
        );
    }

    public function move(string $directory, string $name = null): File
    {
        if (!$this->isValid() || $this->test) {
            return parent::move($directory, $name);
        }

        $target = $this->getTargetFile($directory, $name);

        try {
            $this->psrUploadedFile->moveTo((string) $target);
        } catch (\RuntimeException $e) {
            throw new FileException(sprintf('Could not move the file "%s" to "%s" (%s).', $this->getPathname(), $target, $e->getMessage()), 0, $e);
        }

        @chmod($target, 0666 & ~umask());

        return $target;
    }
}

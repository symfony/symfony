<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UploadedFile implements UploadedFileInterface
{
    public function __construct(
        private readonly string $filePath,
        private readonly ?int $size = null,
        private readonly int $error = \UPLOAD_ERR_OK,
        private readonly ?string $clientFileName = null,
        private readonly ?string $clientMediaType = null,
    ) {
    }

    public function getStream(): StreamInterface
    {
        return new Stream(file_get_contents($this->filePath));
    }

    public function moveTo($targetPath): void
    {
        rename($this->filePath, $targetPath);
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFileName;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}

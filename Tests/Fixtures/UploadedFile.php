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

use Psr\Http\Message\UploadedFileInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UploadedFile implements UploadedFileInterface
{
    private $filePath;
    private $size;
    private $error;
    private $clientFileName;
    private $clientMediaType;

    public function __construct($filePath, $size = null, $error = \UPLOAD_ERR_OK, $clientFileName = null, $clientMediaType = null)
    {
        $this->filePath = $filePath;
        $this->size = $size;
        $this->error = $error;
        $this->clientFileName = $clientFileName;
        $this->clientMediaType = $clientMediaType;
    }

    public function getStream()
    {
        return new Stream(file_get_contents($this->filePath));
    }

    public function moveTo($targetPath)
    {
        rename($this->filePath, $targetPath);
    }

    public function getSize()
    {
        return $this->size;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getClientFilename()
    {
        return $this->clientFileName;
    }

    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }
}

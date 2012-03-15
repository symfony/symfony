<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * BinaryFileResponse represents an HTTP response representing a binary file.
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class BinaryFileResponse extends Response
{
    private $file;

    /**
     * Creates a BinaryFileResponse.
     *
     * @param SplFileInfo|string $file           The file to download
     * @param integer            $status         The response status code
     * @param array              $headers        An array of response headers
     * @param boolean            $autoValidation Whether ETag and Last-Modified headers
     *                                           are automatically set or not
     * @param boolean            $public         Files are public by default
     */
    public function __construct($file, $status = 200, $headers = array(), $autoValidation = true, $public = true)
    {
        parent::__construct('', $status, $headers);

        $this->setFile($file);
        
        $this->headers->set('Content-Transfer-Encoding', 'binary');

        if (true === $autoValidation) {
            $this->setAutoLastModified();
            $this->setAutoEtag();
        }

        if (true === $public) {
            $this->setPublic();
        }
    }

    /**
     * Sets the file to download.
     *
     * Also sets some headers for the download, namely
     * Content-Disposition, Content-Length and Content-Type.
     *
     * @param SplFileInfo|string $file The file to download
     */
    public function setFile($file)
    {
        if (null === $file) {
            throw new \InvalidArgumentException('File cannot be null.');
        }

        $file = new File((string) $file, true);

        if (!$file->isReadable()) {
            throw new FileException('File must be readable.');
        }

        $this->file = $file;

        $this->makeDisposition();
        $this->headers->set('Content-Length', $file->getSize());
        $this->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
    }

    /**
     * Returns the file.
     *
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets the Content-Disposition header.
     *
     * @param string $disposition      Either "inline" or "attachment" (default)
     * @param string $filename         Name of the file (by default the original file name)
     *                                 May be unicode
     * @param string $filenameFallback A string containing only ASCII characters that
     *                                 is semantically equivalent to $filename. If the filename is already ASCII,
     *                                 it can be omitted, or just copied from $filename
     */
    public function makeDisposition($disposition = null, $filename = '', $filenameFallback = '')
    {
        $this->headers->set('Content-Disposition', $this->headers->makeDisposition(
            $disposition ?: ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename ?: $this->file->getBasename(),
            $filenameFallback ?: rawurlencode($this->file->getBasename())
        ));
    }

    /**
     * Automatically sets the Last-Modified header according to the file modification date.
     */
    public function setAutoLastModified()
    {
        $this->setLastModified(\DateTime::createFromFormat('U', $this->file->getMTime()));
    }

    /**
     * Automatically sets the ETag header according to the checksum of the file.
     */
    public function setAutoEtag()
    {
        $this->setEtag(sha1_file($this->file->getPathname()));
    }

    /**
     * Sends the file.
     */
    public function sendContent()
    {
        if (!$this->isSuccessful()) {
            parent::sendContent();
            return;
        }

        readfile($this->file->getPathname());
    }
}

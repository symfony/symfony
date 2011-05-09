<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

/**
 * A file uploaded via a form that has been persisted.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class PersistedFile extends UploadedFile
{
    /**
     * Constructs a new file from the given path.
     *
     * @param string  $path          The path to the persisted file
     * @param string  $originalName  The original file name (from the uploader)
     * @param string  $mimeType      The mime type (from the uploader)
     * @param integer $size          The file size (from the uploader)
     *
     * @throws FileException         If file_uploads is disabled
     * @throws FileNotFoundException If the file does not exist
     */
    public function __construct($path, $originalName = null, $mimeType = null, $size = null)
    {
        parent::__construct($path, $originalName, $mimeType, $size, UPLOAD_ERR_OK);

        $this->persisted = true;
    }
}

<?php

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\FileException;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A file uploaded through a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author     Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class UploadedFile extends File
{
    protected $originalName;
    protected $mimeType;
    protected $size;
    protected $error;
    protected $moved = false;

    /**
     * Accepts the information of the uploaded file as provided by the PHP
     * global $_FILES.
     *
     * @param string  $tmpName  The full temporary path to the file
     * @param string  $name     The original file name
     * @param string  $type     The type of the file as provided by PHP
     * @param integer $size     The file size
     * @param string  $error    The error constant of the upload. Should be
     *                          one of PHP's UPLOAD_XXX constants.
     */
    public function __construct($path, $originalName, $mimeType, $size, $error)
    {
        if (!ini_get('file_uploads')) {
            throw new FileException(sprintf('Unable to create UploadedFile because "file_uploads" is disabled in your php.ini file (%s)', get_cfg_var('cfg_file_path')));
        }

        if (is_file($path)) {
            $this->path = realpath($path);
        }

        if (null === $error) {
            $error = UPLOAD_ERR_OK;
        }

        if (null === $mimeType) {
            $mimeType = 'application/octet-stream';
        }

        $this->originalName = (string)$originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->error = $error;
    }

    /**
     * Returns the mime type of the file.
     *
     * The mime type is guessed using the functions finfo(), mime_content_type()
     * and the system binary "file" (in this order), depending on which of those
     * is available on the current operating system.
     *
     * @returns string  The guessed mime type, e.g. "application/pdf"
     */
    public function getMimeType()
    {
        $mimeType = parent::getMimeType();

        if (null === $mimeType) {
            $mimeType = $this->mimeType;
        }

        return $mimeType;
    }

    /**
     * Returns the original file name including its extension.
     *
     * @returns string  The file name
     */
    public function getOriginalName()
    {
        return $this->originalName;
    }

    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise one of the other UPLOAD_ERR_XXX constants is returned.
     *
     * @returns integer  The upload error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Moves the file to a new location.
     *
     * @param string $newPath
     */
    public function move($newPath)
    {
        if (!$this->moved) {
            if (!move_uploaded_file($this->getPath(), $newPath)) {
                throw new FileException(sprintf('Could not move file %s to %s', $this->getPath(), $newPath));
            }

            $this->moved = true;
            $this->path = realpath($newPath);
        } else {
            parent::move($newPath);
        }
    }
}
<?php

/*
 * This file is part of the Symfony package.
 * 
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\FileException;

/**
 * A file uploaded through a form.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 * @author     Florian Eckerstorfer <florian@eckerstorfer.org>
 */
class UploadedFile extends File
{
    /**
     * The original name of the uploaded file
     * @var string
     */
    protected $originalName;

    /**
     * The mime type provided by the uploader
     * @var string
     */
    protected $mimeType;

    /**
     * The file size provided by the uploader
     * @var integer
     */
    protected $size;

    /**
     * The UPLOAD_ERR_XXX constant provided by the uploader
     * @var integer
     */
    protected $error;

    /**
     * Whether the uploaded file has already been moved
     * @var boolean
     */
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
     *                          one of PHP's UPLOAD_ERR_XXX constants.
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
     * @inheritDoc
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
     * @inheritDoc
     */
    protected function doMove($directory, $filename)
    {
        if (!$this->moved) {
            $newPath = $directory . DIRECTORY_SEPARATOR . $filename;

            if (!move_uploaded_file($this->getPath(), $newPath)) {
                throw new FileException(sprintf('Could not move file %s to %s', $this->getPath(), $newPath));
            }

            $this->moved = true;
            $this->path = realpath($newPath);
        } else {
            parent::doMove($directory, $filename);
        }
    }

    /**
     * @inheritDoc
     */
    public function move($directory, $name = null)
    {
        if (!$this->moved) {
            $this->doMove($directory, $this->originalName);

            if (null !== $name) {
                $this->rename($name);
            }
        } else {
            parent::move($directory, $name);
        }
    }
}
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

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\MimeType\ExtensionGuesser;

/**
 * A file uploaded through a form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UploadedFile extends File
{
    private $test;
    private $originalName;
    private $mimeType;
    private $size;
    private $error;

    /**
     * Accepts the information of the uploaded file as provided by the PHP global $_FILES.
     *
     * The file object is only created when the uploaded file is valid (i.e. when the
     * isValid() method returns true). Otherwise the only methods that could be called
     * on an UploadedFile instance are:
     *
     *   * getClientOriginalName,
     *   * getClientMimeType,
     *   * isValid,
     *   * getError.
     *
     * Calling any other method on an non-valid instance will cause an unpredictable result.
     *
     * @param string      $path         The full temporary path to the file
     * @param string      $originalName The original file name of the uploaded file
     * @param string|null $mimeType     The type of the file as provided by PHP; null defaults to application/octet-stream
     * @param int|null    $size         The file size provided by the uploader
     * @param int|null    $error        The error constant of the upload (one of PHP's UPLOAD_ERR_XXX constants); null defaults to UPLOAD_ERR_OK
     * @param bool        $test         Whether the test mode is active
     *                                  Local files are used in test mode hence the code should not enforce HTTP uploads
     *
     * @throws FileException         If file_uploads is disabled
     * @throws FileNotFoundException If the file does not exist
     */
    public function __construct($path, $originalName, $mimeType = null, $size = null, $error = null, $test = false)
    {
        $this->originalName = $this->getName($originalName);
        $this->mimeType = $mimeType ?: 'application/octet-stream';
        $this->size = $size;
        $this->error = $error ?: UPLOAD_ERR_OK;
        $this->test = (bool) $test;

        parent::__construct($path, UPLOAD_ERR_OK === $this->error);
    }

    /**
     * Returns the original file name.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string The original name
     */
    public function getClientOriginalName()
    {
        return $this->originalName;
    }

    /**
     * Returns the original file extension.
     *
     * It is extracted from the original file name that was uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return string The extension
     */
    public function getClientOriginalExtension()
    {
        return pathinfo($this->originalName, PATHINFO_EXTENSION);
    }

    /**
     * Returns the file mime type.
     *
     * The client mime type is extracted from the request from which the file
     * was uploaded, so it should not be considered as a safe value.
     *
     * For a trusted mime type, use getMimeType() instead (which guesses the mime
     * type based on the file content).
     *
     * @return string The mime type
     *
     * @see getMimeType()
     */
    public function getClientMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Returns the extension based on the client mime type.
     *
     * If the mime type is unknown, returns null.
     *
     * This method uses the mime type as guessed by getClientMimeType()
     * to guess the file extension. As such, the extension returned
     * by this method cannot be trusted.
     *
     * For a trusted extension, use guessExtension() instead (which guesses
     * the extension based on the guessed mime type for the file).
     *
     * @return string|null The guessed extension or null if it cannot be guessed
     *
     * @see guessExtension()
     * @see getClientMimeType()
     */
    public function guessClientExtension()
    {
        $type = $this->getClientMimeType();
        $guesser = ExtensionGuesser::getInstance();

        return $guesser->guess($type);
    }

    /**
     * Returns the file size.
     *
     * It is extracted from the request from which the file has been uploaded.
     * Then it should not be considered as a safe value.
     *
     * @return int|null The file size
     */
    public function getClientSize()
    {
        return $this->size;
    }

    /**
     * Returns the upload error.
     *
     * If the upload was successful, the constant UPLOAD_ERR_OK is returned.
     * Otherwise one of the other UPLOAD_ERR_XXX constants is returned.
     *
     * @return int The upload error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns whether the file was uploaded successfully.
     *
     * @return bool True if the file has been uploaded with HTTP and no error occurred
     */
    public function isValid()
    {
        $isOk = UPLOAD_ERR_OK === $this->error;

        return $this->test ? $isOk : $isOk && is_uploaded_file($this->getPathname());
    }

    /**
     * Moves the file to a new location.
     *
     * @param string $directory The destination folder
     * @param string $name      The new file name
     *
     * @return File A File object representing the new file
     *
     * @throws FileException if, for any reason, the file could not have been moved
     */
    public function move($directory, $name = null)
    {
        if ($this->isValid()) {
            if ($this->test) {
                return parent::move($directory, $name);
            }

            $target = $this->getTargetFile($directory, $name);

            set_error_handler(function ($type, $msg) use (&$error) { $error = $msg; });
            $moved = move_uploaded_file($this->getPathname(), $target);
            restore_error_handler();
            if (!$moved) {
                throw new FileException(sprintf('Could not move the file "%s" to "%s" (%s).', $this->getPathname(), $target, strip_tags($error)));
            }

            @chmod($target, 0666 & ~umask());

            return $target;
        }

        throw new FileException($this->getErrorMessage());
    }

    /**
     * Returns the maximum size of an uploaded file as configured in php.ini.
     *
     * @return int The maximum size of an uploaded file in bytes
     */
    public static function getMaxFilesize()
    {
        $sizePostMax = self::parseFilesize(ini_get('post_max_size'));
        $sizeUploadMax = self::parseFilesize(ini_get('upload_max_filesize'));

        return min($sizePostMax ?: PHP_INT_MAX, $sizeUploadMax ?: PHP_INT_MAX);
    }

    /**
     * Returns the given size from an ini value in bytes.
     *
     * @return int The given size in bytes
     */
    private static function parseFilesize($size)
    {
        if ('' === $size) {
            return 0;
        }

        $size = strtolower($size);

        $max = ltrim($size, '+');
        if (0 === strpos($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($size, -1)) {
            case 't': $max *= 1024;
            // no break
            case 'g': $max *= 1024;
            // no break
            case 'm': $max *= 1024;
            // no break
            case 'k': $max *= 1024;
        }

        return $max;
    }

    /**
     * Returns an informative upload error message.
     *
     * @return string The error message regarding the specified error code
     */
    public function getErrorMessage()
    {
        static $errors = [
            UPLOAD_ERR_INI_SIZE => 'The file "%s" exceeds your upload_max_filesize ini directive (limit is %d KiB).',
            UPLOAD_ERR_FORM_SIZE => 'The file "%s" exceeds the upload limit defined in your form.',
            UPLOAD_ERR_PARTIAL => 'The file "%s" was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_CANT_WRITE => 'The file "%s" could not be written on disk.',
            UPLOAD_ERR_NO_TMP_DIR => 'File could not be uploaded: missing temporary directory.',
            UPLOAD_ERR_EXTENSION => 'File upload was stopped by a PHP extension.',
        ];

        $errorCode = $this->error;
        $maxFilesize = UPLOAD_ERR_INI_SIZE === $errorCode ? self::getMaxFilesize() / 1024 : 0;
        $message = isset($errors[$errorCode]) ? $errors[$errorCode] : 'The file "%s" was not uploaded due to an unknown error.';

        return sprintf($message, $this->getClientOriginalName(), $maxFilesize);
    }
}

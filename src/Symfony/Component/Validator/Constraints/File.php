<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class File extends Constraint
{
    // Check the Image constraint for clashes if adding new constants here

    const NOT_FOUND_ERROR = 1;
    const NOT_READABLE_ERROR = 2;
    const EMPTY_ERROR = 3;
    const TOO_LARGE_ERROR = 4;
    const INVALID_MIME_TYPE_ERROR = 5;

    protected static $errorNames = array(
        self::NOT_FOUND_ERROR => 'NOT_FOUND_ERROR',
        self::NOT_READABLE_ERROR => 'NOT_READABLE_ERROR',
        self::EMPTY_ERROR => 'EMPTY_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_MIME_TYPE_ERROR => 'INVALID_MIME_TYPE_ERROR',
    );

    public $binaryFormat;
    public $mimeTypes = array();
    public $notFoundMessage = 'The file could not be found.';
    public $notReadableMessage = 'The file is not readable.';
    public $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
    public $disallowEmptyMessage = 'An empty file is not allowed.';

    public $uploadIniSizeErrorMessage = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $uploadFormSizeErrorMessage = 'The file is too large.';
    public $uploadPartialErrorMessage = 'The file was only partially uploaded.';
    public $uploadNoFileErrorMessage = 'No file was uploaded.';
    public $uploadNoTmpDirErrorMessage = 'No temporary folder was configured in php.ini.';
    public $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';
    public $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';
    public $uploadErrorMessage = 'The file could not be uploaded.';

    protected $maxSize;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null !== $this->maxSize) {
            $this->normalizeBinaryFormat($this->maxSize);
        }
    }

    public function __set($option, $value)
    {
        if ('maxSize' === $option) {
            $this->normalizeBinaryFormat($value);

            return;
        }

        parent::__set($option, $value);
    }

    public function __get($option)
    {
        if ('maxSize' === $option) {
            return $this->maxSize;
        }

        return parent::__get($option);
    }

    private function normalizeBinaryFormat($maxSize)
    {
        $sizeInt = (int) $maxSize;

        if (ctype_digit((string) $maxSize)) {
            $this->maxSize = $sizeInt;
            $this->binaryFormat = null === $this->binaryFormat ? false : $this->binaryFormat;
        } elseif (preg_match('/^\d++k$/i', $maxSize)) {
            $this->maxSize = $sizeInt * 1000;
            $this->binaryFormat = null === $this->binaryFormat ? false : $this->binaryFormat;
        } elseif (preg_match('/^\d++M$/i', $maxSize)) {
            $this->maxSize = $sizeInt * 1000000;
            $this->binaryFormat = null === $this->binaryFormat ? false : $this->binaryFormat;
        } elseif (preg_match('/^\d++Ki$/i', $maxSize)) {
            $this->maxSize = $sizeInt << 10;
            $this->binaryFormat = null === $this->binaryFormat ? true : $this->binaryFormat;
        } elseif (preg_match('/^\d++Mi$/i', $maxSize)) {
            $this->maxSize = $sizeInt << 20;
            $this->binaryFormat = null === $this->binaryFormat ? true : $this->binaryFormat;
        } else {
            throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $this->maxSize));
        }
    }
}

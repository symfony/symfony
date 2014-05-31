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
 *
 * @api
 */
class File extends Constraint
{
    const SIZE_FORMAT_BINARY = 2;
    const SIZE_FORMAT_DECIMAL = 10;

    public $maxSize = null;
    public $maxSizeFormat = self::SIZE_FORMAT_BINARY;
    public $mimeTypes = array();
    public $notFoundMessage = 'The file could not be found.';
    public $notReadableMessage = 'The file is not readable.';
    public $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';

    public $uploadIniSizeErrorMessage   = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $uploadFormSizeErrorMessage  = 'The file is too large.';
    public $uploadPartialErrorMessage   = 'The file was only partially uploaded.';
    public $uploadNoFileErrorMessage    = 'No file was uploaded.';
    public $uploadNoTmpDirErrorMessage  = 'No temporary folder was configured in php.ini.';
    public $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';
    public $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';
    public $uploadErrorMessage          = 'The file could not be uploaded.';

    public function __construct($options = null)
    {
        if (is_array($options) && array_key_exists('maxSizeFormat', $options)) {
            throw new ConstraintDefinitionException(sprintf(
                'The option "maxSizeFormat" is not supported by the constraint %s',
                __CLASS__
            ));
        }

        parent::__construct($options);

        if ($this->maxSize) {
            if (ctype_digit((string) $this->maxSize)) {
                $this->maxSize = (int) $this->maxSize;
                $this->maxSizeFormat = self::SIZE_FORMAT_DECIMAL;
            } elseif (preg_match('/^\d++k$/i', $this->maxSize)) {
                $this->maxSize = $this->maxSize * 1000;
                $this->maxSizeFormat = self::SIZE_FORMAT_DECIMAL;
            } elseif (preg_match('/^\d++M$/i', $this->maxSize)) {
                $this->maxSize = $this->maxSize * 1000000;
                $this->maxSizeFormat = self::SIZE_FORMAT_DECIMAL;
            } elseif (preg_match('/^\d++ki$/i', $this->maxSize)) {
                $this->maxSize = $this->maxSize << 10;
                $this->maxSizeFormat = self::SIZE_FORMAT_BINARY;
            } elseif (preg_match('/^\d++Mi$/i', $this->maxSize)) {
                $this->maxSize = $this->maxSize << 20;
                $this->maxSizeFormat = self::SIZE_FORMAT_BINARY;
            } else {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $this->maxSize));
            }
        }
    }
}

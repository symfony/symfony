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

use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class FileValidator extends ConstraintValidator
{
    const KB_BYTES = 1000;
    const MB_BYTES = 1000000;
    const KIB_BYTES = 1024;
    const MIB_BYTES = 1048576;

    private static $suffices = array(
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    );

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof File) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\File');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if ($value instanceof UploadedFile && !$value->isValid()) {
            switch ($value->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    if ($constraint->maxSize) {
                        $limitInBytes = min(UploadedFile::getMaxFilesize(), $constraint->maxSize);
                    } else {
                        $limitInBytes = UploadedFile::getMaxFilesize();
                    }

                    $this->context->addViolation($constraint->uploadIniSizeErrorMessage, array(
                        '{{ limit }}' => $limitInBytes,
                        '{{ suffix }}' => 'bytes',
                    ));

                    return;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->context->addViolation($constraint->uploadFormSizeErrorMessage);

                    return;
                case UPLOAD_ERR_PARTIAL:
                    $this->context->addViolation($constraint->uploadPartialErrorMessage);

                    return;
                case UPLOAD_ERR_NO_FILE:
                    $this->context->addViolation($constraint->uploadNoFileErrorMessage);

                    return;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->context->addViolation($constraint->uploadNoTmpDirErrorMessage);

                    return;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->context->addViolation($constraint->uploadCantWriteErrorMessage);

                    return;
                case UPLOAD_ERR_EXTENSION:
                    $this->context->addViolation($constraint->uploadExtensionErrorMessage);

                    return;
                default:
                    $this->context->addViolation($constraint->uploadErrorMessage);

                    return;
            }
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $path = $value instanceof FileObject ? $value->getPathname() : (string) $value;

        if (!is_file($path)) {
            $this->context->addViolation($constraint->notFoundMessage, array('{{ file }}' => $path));

            return;
        }

        if (!is_readable($path)) {
            $this->context->addViolation($constraint->notReadableMessage, array('{{ file }}' => $path));

            return;
        }

        $sizeInBytes = filesize($path);
        if (0 === $sizeInBytes) {
            $this->context->addViolation($constraint->disallowEmptyMessage);
        } elseif ($constraint->maxSize) {
            $limitInBytes = $constraint->maxSize;

            if ($sizeInBytes > $limitInBytes) {
                // Convert the limit to the smallest possible number
                // (i.e. try "MB", then "kB", then "bytes")
                if ($constraint->binaryFormat) {
                    $coef = self::MIB_BYTES;
                    $coefFactor = self::KIB_BYTES;
                } else {
                    $coef = self::MB_BYTES;
                    $coefFactor = self::KB_BYTES;
                }

                $limitAsString = (string) ($limitInBytes / $coef);

                // Restrict the limit to 2 decimals (without rounding! we
                // need the precise value)
                while (self::moreDecimalsThan($limitAsString, 2)) {
                    $coef /= $coefFactor;
                    $limitAsString = (string) ($limitInBytes / $coef);
                }

                // Convert size to the same measure, but round to 2 decimals
                $sizeAsString = (string) round($sizeInBytes / $coef, 2);

                // If the size and limit produce the same string output
                // (due to rounding), reduce the coefficient
                while ($sizeAsString === $limitAsString) {
                    $coef /= $coefFactor;
                    $limitAsString = (string) ($limitInBytes / $coef);
                    $sizeAsString = (string) round($sizeInBytes / $coef, 2);
                }

                $this->context->addViolation($constraint->maxSizeMessage, array(
                    '{{ size }}'    => $sizeAsString,
                    '{{ limit }}'   => $limitAsString,
                    '{{ suffix }}'  => self::$suffices[$coef],
                    '{{ file }}'    => $path,
                ));

                return;
            }
        }

        if ($constraint->mimeTypes) {
            if (!$value instanceof FileObject) {
                $value = new FileObject($value);
            }

            $mimeTypes = (array) $constraint->mimeTypes;
            $mime = $value->getMimeType();
            $valid = false;

            foreach ($mimeTypes as $mimeType) {
                if ($mimeType === $mime) {
                    $valid = true;
                    break;
                }

                if ($discrete = strstr($mimeType, '/*', true)) {
                    if (strstr($mime, '/', true) === $discrete) {
                        $valid = true;
                        break;
                    }
                }
            }

            if (false === $valid) {
                $this->context->addViolation($constraint->mimeTypesMessage, array(
                    '{{ type }}'    => '"'.$mime.'"',
                    '{{ types }}'   => '"'.implode('", "', $mimeTypes) .'"',
                    '{{ file }}'    => $path,
                ));
            }
        }
    }

    private static function moreDecimalsThan($double, $numberOfDecimals)
    {
        return strlen((string) $double) > strlen(round($double, $numberOfDecimals));
    }
}

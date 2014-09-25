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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
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

    private static $suffices = array(
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
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
                        if (ctype_digit((string) $constraint->maxSize)) {
                            $limitInBytes = (int) $constraint->maxSize;
                        } elseif (preg_match('/^\d++k$/', $constraint->maxSize)) {
                            $limitInBytes = $constraint->maxSize * self::KB_BYTES;
                        } elseif (preg_match('/^\d++M$/', $constraint->maxSize)) {
                            $limitInBytes = $constraint->maxSize * self::MB_BYTES;
                        } else {
                            throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
                        }
                        $limitInBytes = min(UploadedFile::getMaxFilesize(), $limitInBytes);
                    } else {
                        $limitInBytes = UploadedFile::getMaxFilesize();
                    }

                    $this->buildViolation($constraint->uploadIniSizeErrorMessage)
                        ->setParameter('{{ limit }}', $limitInBytes)
                        ->setParameter('{{ suffix }}', 'bytes')
                        ->addViolation();

                    return;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->buildViolation($constraint->uploadFormSizeErrorMessage)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_PARTIAL:
                    $this->buildViolation($constraint->uploadPartialErrorMessage)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_NO_FILE:
                    $this->buildViolation($constraint->uploadNoFileErrorMessage)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->buildViolation($constraint->uploadNoTmpDirErrorMessage)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->buildViolation($constraint->uploadCantWriteErrorMessage)
                        ->addViolation();

                    return;
                case UPLOAD_ERR_EXTENSION:
                    $this->buildViolation($constraint->uploadExtensionErrorMessage)
                        ->addViolation();

                    return;
                default:
                    $this->buildViolation($constraint->uploadErrorMessage)
                        ->setCode($value->getError())
                        ->addViolation();

                    return;
            }
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $path = $value instanceof FileObject ? $value->getPathname() : (string) $value;

        if (!is_file($path)) {
            $this->buildViolation($constraint->notFoundMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->addViolation();

            return;
        }

        if (!is_readable($path)) {
            $this->buildViolation($constraint->notReadableMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->addViolation();

            return;
        }

        if ($constraint->maxSize) {
            $sizeInBytes = filesize($path);
            $limitInBytes = (int) $constraint->maxSize;

            if (preg_match('/^\d++k$/', $constraint->maxSize)) {
                $limitInBytes *= self::KB_BYTES;
            } elseif (preg_match('/^\d++M$/', $constraint->maxSize)) {
                $limitInBytes *= self::MB_BYTES;
            } elseif (!ctype_digit((string) $constraint->maxSize)) {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
            }

            if ($sizeInBytes > $limitInBytes) {
                // Convert the limit to the smallest possible number
                // (i.e. try "MB", then "kB", then "bytes")
                $coef = self::MB_BYTES;
                $limitAsString = (string) ($limitInBytes / $coef);

                // Restrict the limit to 2 decimals (without rounding! we
                // need the precise value)
                while (self::moreDecimalsThan($limitAsString, 2)) {
                    $coef /= 1000;
                    $limitAsString = (string) ($limitInBytes / $coef);
                }

                // Convert size to the same measure, but round to 2 decimals
                $sizeAsString = (string) round($sizeInBytes / $coef, 2);

                // If the size and limit produce the same string output
                // (due to rounding), reduce the coefficient
                while ($sizeAsString === $limitAsString) {
                    $coef /= 1000;
                    $limitAsString = (string) ($limitInBytes / $coef);
                    $sizeAsString = (string) round($sizeInBytes / $coef, 2);
                }

                $this->buildViolation($constraint->maxSizeMessage)
                    ->setParameter('{{ file }}', $this->formatValue($path))
                    ->setParameter('{{ size }}', $sizeAsString)
                    ->setParameter('{{ limit }}', $limitAsString)
                    ->setParameter('{{ suffix }}', self::$suffices[$coef])
                    ->addViolation();

                return;
            }
        }

        if ($constraint->mimeTypes) {
            if (!$value instanceof FileObject) {
                $value = new FileObject($value);
            }

            $mimeTypes = (array) $constraint->mimeTypes;
            $mime = $value->getMimeType();

            foreach ($mimeTypes as $mimeType) {
                if ($mimeType === $mime) {
                    return;
                }

                if ($discrete = strstr($mimeType, '/*', true)) {
                    if (strstr($mime, '/', true) === $discrete) {
                        return;
                    }
                }
            }

            $this->buildViolation($constraint->mimeTypesMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->setParameter('{{ type }}', $this->formatValue($mime))
                ->setParameter('{{ types }}', $this->formatValues($mimeTypes))
                ->addViolation();
        }
    }

    private static function moreDecimalsThan($double, $numberOfDecimals)
    {
        return strlen((string) $double) > strlen(round($double, $numberOfDecimals));
    }
}

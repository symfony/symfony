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
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class FileValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        if ($value instanceof UploadedFile && !$value->isValid()) {
            switch ($value->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    if ($constraint->maxSize) {
                        if (ctype_digit((string) $constraint->maxSize)) {
                            $maxSize = (int) $constraint->maxSize;
                        } elseif (preg_match('/^\d++k$/', $constraint->maxSize)) {
                            $maxSize = $constraint->maxSize * 1024;
                        } elseif (preg_match('/^\d++M$/', $constraint->maxSize)) {
                            $maxSize = $constraint->maxSize * 1048576;
                        } else {
                            throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
                        }
                        $maxSize = min(UploadedFile::getMaxFilesize(), $maxSize);
                    } else {
                        $maxSize = UploadedFile::getMaxFilesize();
                    }

                    $this->context->addViolation($constraint->uploadIniSizeErrorMessage, array(
                        '{{ limit }}' => $maxSize,
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

        if ($constraint->maxSize) {
            if (ctype_digit((string) $constraint->maxSize)) {
                $size = filesize($path);
                $limit = (int) $constraint->maxSize;
                $suffix = 'bytes';
            } elseif (preg_match('/^\d++k$/', $constraint->maxSize)) {
                $size = round(filesize($path) / 1000, 2);
                $limit = (int) $constraint->maxSize;
                $suffix = 'kB';
            } elseif (preg_match('/^\d++M$/', $constraint->maxSize)) {
                $size = round(filesize($path) / 1000000, 2);
                $limit = (int) $constraint->maxSize;
                $suffix = 'MB';
            } else {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
            }

            if ($size > $limit) {
                $this->context->addViolation($constraint->maxSizeMessage, array(
                    '{{ size }}'    => $size,
                    '{{ limit }}'   => $limit,
                    '{{ suffix }}'  => $suffix,
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
}

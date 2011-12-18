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
 * @api
 */
class FileValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     *
     * @return Boolean Whether or not the value is valid
     *
     * @api
     */
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if ($value instanceof UploadedFile && !$value->isValid()) {
            switch ($value->getError()) {
                case UPLOAD_ERR_INI_SIZE:
                    $maxSize = UploadedFile::getMaxFilesize();
                    $maxSize = $constraint->maxSize ? min($maxSize, $constraint->maxSize) : $maxSize;
                    $this->setMessage($constraint->uploadIniSizeErrorMessage, array('{{ limit }}' => $maxSize.' bytes'));

                    return false;
                case UPLOAD_ERR_FORM_SIZE:
                    $this->setMessage($constraint->uploadFormSizeErrorMessage);

                    return false;
                default:
                    $this->setMessage($constraint->uploadErrorMessage);

                    return false;
            }
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $path = $value instanceof FileObject ? $value->getPathname() : (string) $value;

        if (!file_exists($path)) {
            $this->setMessage($constraint->notFoundMessage, array('{{ file }}' => $path));

            return false;
        }

        if (!is_readable($path)) {
            $this->setMessage($constraint->notReadableMessage, array('{{ file }}' => $path));

            return false;
        }

        if ($constraint->maxSize) {
            if (ctype_digit((string) $constraint->maxSize)) {
                $size = filesize($path);
                $limit = $constraint->maxSize;
                $suffix = ' bytes';
            } elseif (preg_match('/^(\d+)k$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000, 2);
                $limit = $matches[1];
                $suffix = ' kB';
            } elseif (preg_match('/^(\d+)M$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000000, 2);
                $limit = $matches[1];
                $suffix = ' MB';
            } else {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
            }

            if ($size > $limit) {
                $this->setMessage($constraint->maxSizeMessage, array(
                    '{{ size }}'    => $size.$suffix,
                    '{{ limit }}'   => $limit.$suffix,
                    '{{ file }}'    => $path,
                ));

                return false;
            }
        }

        if ($constraint->mimeTypes) {
            if (!$value instanceof FileObject) {
                $value = new FileObject($value);
            }

            if (!in_array($value->getMimeType(), (array) $constraint->mimeTypes)) {
                $this->setMessage($constraint->mimeTypesMessage, array(
                    '{{ type }}'    => '"'.$value->getMimeType().'"',
                    '{{ types }}'   => '"'.implode('", "', (array) $constraint->mimeTypes).'"',
                    '{{ file }}'    => $path,
                ));

                return false;
            }
        }

        return true;
    }
}

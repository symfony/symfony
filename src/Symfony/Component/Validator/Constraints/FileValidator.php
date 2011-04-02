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

class FileValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return true;
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ($value instanceof FileObject && null === $value->getPath()) {
            return true;
        }

        $path = $value instanceof FileObject ? $value->getPath() : (string) $value;

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
            } else if (preg_match('/^(\d+)k$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000, 2);
                $limit = $matches[1];
                $suffix = ' kB';
            } else if (preg_match('/^(\d+)M$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000000, 2);
                $limit = $matches[1];
                $suffix = ' MB';
            } else {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
            }

            if ($size > $limit) {
                $this->setMessage($constraint->maxSizeMessage, array(
                    '{{ size }}' => $size . $suffix,
                    '{{ limit }}' => $limit . $suffix,
                    '{{ file }}' => $path,
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
                    '{{ type }}' => '"'.$value->getMimeType().'"',
                    '{{ types }}' => '"'.implode('", "', (array) $constraint->mimeTypes).'"',
                    '{{ file }}' => $path,
                ));

                return false;
            }
        }

        return true;
    }
}

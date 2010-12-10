<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\HttpFoundation\File\File as FileObject;

class FileValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if ($value instanceof FileObject && null === $value->getPath()) {
            return true;
        }

        $path = $value instanceof FileObject ? $value->getPath() : (string)$value;

        if (!file_exists($path)) {
            $this->setMessage($constraint->notFoundMessage, array('{{ file }}' => $path));

            return false;
        }

        if (!is_readable($path)) {
            $this->setMessage($constraint->notReadableMessage, array('{{ file }}' => $path));

            return false;
        }

        if ($constraint->maxSize) {
            if (ctype_digit((string)$constraint->maxSize)) {
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
                throw new ConstraintValidationException();
            }

            if (!in_array($value->getMimeType(), (array)$constraint->mimeTypes)) {
                $this->setMessage($constraint->mimeTypesMessage, array(
                    '{{ type }}' => '"'.$value->getMimeType().'"',
                    '{{ types }}' => '"'.implode('", "', (array)$constraint->mimeTypes).'"',
                    '{{ file }}' => $path,
                ));

                return false;
            }
        }

        return true;
    }
}

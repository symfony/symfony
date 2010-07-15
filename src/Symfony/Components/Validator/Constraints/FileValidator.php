<?php

namespace Symfony\Components\Validator\Constraints;

use Symfony\Components\Validator\Constraint;
use Symfony\Components\Validator\ConstraintValidator;
use Symfony\Components\Validator\Exception\ConstraintDefinitionException;
use Symfony\Components\Validator\Exception\UnexpectedTypeException;
use Symfony\Components\File\File as FileObject;

class FileValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if ($value === null) {
            return true;
        }

        if (!is_scalar($value) && !$value instanceof FileObject && !(is_object($value) && method_exists($value, '__toString()'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $path = $value instanceof FileObject ? $value->getPath() : (string)$value;

        if (!file_exists($path)) {
            $this->setMessage($constraint->notFoundMessage, array('file' => $path));

            return false;
        }

        if (!is_readable($path)) {
            $this->setMessage($constraint->notReadableMessage, array('file' => $path));

            return false;
        }

        if ($constraint->maxSize) {
            if (ctype_digit((string)$constraint->maxSize)) {
                $size = filesize($path);
                $limit = $constraint->maxSize;
                $suffix = ' bytes';
            } else if (preg_match('/^(\d)k$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000, 2);
                $limit = $matches[1];
                $suffix = ' kB';
            } else if (preg_match('/^(\d)M$/', $constraint->maxSize, $matches)) {
                $size = round(filesize($path) / 1000000, 2);
                $limit = $matches[1];
                $suffix = ' MB';
            } else {
                throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size', $constraint->maxSize));
            }

            if ($size > $limit) {
                $this->setMessage($constraint->maxSizeMessage, array(
                    'size' => $size . $suffix,
                    'limit' => $limit . $suffix,
                    'file' => $path,
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
                    'type' => '"'.$value->getMimeType().'"',
                    'types' => '"'.implode('", "', (array)$constraint->mimeTypes).'"',
                    'file' => $path,
                ));

                return false;
            }
        }

        return true;
    }
}

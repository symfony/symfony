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
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
class XmlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Xml) {
            throw new UnexpectedTypeException($constraint, Xml::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;
        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            // First, check if XML is well-formed
            if (false === $doc = simplexml_load_string($value)) {
                $this->context->buildViolation($constraint->formatMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->setCode(Xml::INVALID_XML_ERROR)
                    ->addViolation();

                libxml_clear_errors();

                return;
            }

            // Second, validate against XML Schema if schemaPath provided
            if ($schemaPath = $constraint->schemaPath) {
                $dom = dom_import_simplexml($doc)->ownerDocument;
                libxml_clear_errors();
                $schemaPath = realpath($schemaPath) ?: $schemaPath;

                if (@$dom->schemaValidate($schemaPath, $constraint->schemaFlags)) {
                    return;
                }
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $schemaPathNormalized = str_replace(\DIRECTORY_SEPARATOR, '/', $schemaPath);

                foreach ($errors as $error) {
                    if ($schemaPathNormalized && str_ends_with($error->file, $schemaPathNormalized)) {
                        throw new InvalidArgumentException(\sprintf('The XSD schema file "%s" is not valid.', $schemaPath));
                    }
                    $schemaPathNormalized = false;
                    $this->context->buildViolation($constraint->schemaMessage)
                        ->setParameter('{{ error }}', $error->message)
                        ->setParameter('{{ line }}', $error->line)
                        ->setCode(Xml::INVALID_XML_ERROR)
                        ->addViolation();
                }
            }
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }
    }
}

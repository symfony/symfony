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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class Base64Validator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Base64) {
            throw new UnexpectedTypeException($constraint, Base64::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        if ($constraint->urlEncoded) {
            $value = rawurldecode($value);
        }

        if ($constraint->requiresDataUri) {
            preg_match('/^data:.+;base64,/', $value, $matches);

            if (0 === \count($matches)) {
                $this->context->buildViolation($constraint->messageMissingDataUri)
                    ->setCode(Base64::MISSING_DATA_URI_ERROR)
                    ->addViolation();

                return;
            }

            $value = str_replace($matches[0], '', $value);
        }

        preg_match('/^[a-zA-Z0-9\/\r\n+]*(==)?$/', $value, $matches);
        if (0 === \count($matches)) {
            $this->context->buildViolation($constraint->messageInvalidString)
                ->setCode(Base64::INVALID_STRING_ERROR)
                ->addViolation();
        }
    }
}

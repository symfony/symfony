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
 * Validates whether the value is a valid ULID (Universally Unique Lexicographically Sortable Identifier).
 * Cf https://github.com/ulid/spec for ULID specifications.
 *
 * @author Laurent Clouet <laurent35240@gmail.com>
 */
class UlidValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Ulid) {
            throw new UnexpectedTypeException($constraint, Ulid::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        [$requiredLength, $requiredCharset] = match ($constraint->format) {
            Ulid::FORMAT_BASE_32 => [26, '0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz'],
            Ulid::FORMAT_BASE_58 => [22, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'],
            Ulid::FORMAT_RFC_4122 => [36, '0123456789ABCDEFabcdef-'],
        };

        if ($requiredLength !== \strlen($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameters([
                    '{{ value }}' => $this->formatValue($value),
                    '{{ format }}' => $constraint->format,
                ])
                ->setCode($requiredLength > \strlen($value) ? Ulid::TOO_SHORT_ERROR : Ulid::TOO_LONG_ERROR)
                ->addViolation();

            return;
        }

        if (\strlen($value) !== strspn($value, $requiredCharset)) {
            $this->context->buildViolation($constraint->message)
                ->setParameters([
                    '{{ value }}' => $this->formatValue($value),
                    '{{ format }}' => $constraint->format,
                ])
                ->setCode(Ulid::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        if (Ulid::FORMAT_BASE_32 === $constraint->format) {
            // Largest valid ULID is '7ZZZZZZZZZZZZZZZZZZZZZZZZZ'
            // Cf https://github.com/ulid/spec#overflow-errors-when-parsing-base32-strings
            if ($value[0] > '7') {
                $this->context->buildViolation($constraint->message)
                    ->setParameters([
                        '{{ value }}' => $this->formatValue($value),
                        '{{ format }}' => $constraint->format,
                    ])
                    ->setCode(Ulid::TOO_LARGE_ERROR)
                    ->addViolation();
            }
        } elseif (Ulid::FORMAT_RFC_4122 === $constraint->format) {
            if (!preg_match('/^[^-]{8}-[^-]{4}-[^-]{4}-[^-]{4}-[^-]{12}$/', $value)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameters([
                        '{{ value }}' => $this->formatValue($value),
                        '{{ format }}' => $constraint->format,
                    ])
                    ->setCode(Ulid::INVALID_FORMAT_ERROR)
                    ->addViolation();
            }
        }
    }
}

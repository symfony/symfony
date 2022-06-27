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
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Ulid) {
            throw new UnexpectedTypeException($constraint, Ulid::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_scalar($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (26 !== \strlen($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(26 > \strlen($value) ? Ulid::TOO_SHORT_ERROR : Ulid::TOO_LONG_ERROR)
                ->addViolation();
        }

        if (\strlen($value) !== strspn($value, '0123456789ABCDEFGHJKMNPQRSTVWXYZabcdefghjkmnpqrstvwxyz')) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Ulid::INVALID_CHARACTERS_ERROR)
                ->addViolation();
        }

        // Largest valid ULID is '7ZZZZZZZZZZZZZZZZZZZZZZZZZ'
        // Cf https://github.com/ulid/spec#overflow-errors-when-parsing-base32-strings
        if ($value[0] > '7') {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Ulid::TOO_LARGE_ERROR)
                ->addViolation();
        }
    }
}

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

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
final class PhoneNumberValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PhoneNumber) {
            throw new UnexpectedTypeException($constraint, PhoneNumber::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $value = (string) $value;

        if (null !== $constraint->normalizer) {
            $value = ($constraint->normalizer)($value);
        }

        if (!preg_match('/^\+[1-9]\d{1,14}$/D', $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(PhoneNumber::INVALID_FORMAT_ERROR)
                ->addViolation();

            return;
        }

        if (PhoneNumber::MODE_STRICT !== $constraint->mode) {
            return;
        }

        $phoneNumberUtil = PhoneNumberUtil::getInstance();

        try {
            // E.164 numbers carry their region in the "+" prefix, so no default region is needed
            $isValid = $phoneNumberUtil->isValidNumber($phoneNumberUtil->parse($value, null));
        } catch (NumberParseException) {
            $isValid = false;
        }

        if (!$isValid) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(PhoneNumber::INVALID_PHONE_NUMBER_ERROR)
                ->addViolation();
        }
    }
}

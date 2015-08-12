<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Michael Hirschler <michael.vhirsch@gmail.com>
 *
 * @link https://en.wikipedia.org/wiki/ISO_9362#Structure
 *
 * @api
 */
class BicValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value || '' === $value) {
            return;
        }

        $canonicalize = str_replace(' ', '', $value);

        // the bic must have either 8 or 11 characters
        if (8 !== strlen($canonicalize) && 11 !== strlen($canonicalize)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_LENGTH_ERROR)
                ->addViolation();
        }

        // must contain alphanumeric values only
        if (!ctype_alnum($canonicalize)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_CHARACTERS_ERROR)
                ->addViolation();
        }

        // first 4 letters must be alphabetic (bank code)
        if (!ctype_alpha(substr($canonicalize, 0, 4))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_BANK_CODE_ERROR)
                ->addViolation();
        }

        // next 2 letters must be alphabetic (country code)
        if (!ctype_alpha(substr($canonicalize, 4, 2))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_COUNTRY_CODE_ERROR)
                ->addViolation();
        }

        // should contain uppercase characters only
        if (strtoupper($canonicalize) !== $canonicalize) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Bic::INVALID_CASE_ERROR)
                ->addViolation();
        }
    }
}

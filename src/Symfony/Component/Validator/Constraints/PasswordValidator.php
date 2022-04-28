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

/**
 * @author Jérémy Reynaud <jeremy@reynaud.io>
 */
class PasswordValidator extends ConstraintValidator
{
    /** {@inheritDoc} */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof Password) {
            throw new UnexpectedTypeException($constraint, Password::class);
        }

        $value = (string) $value;

        if ($constraint->min > mb_strlen($value)) {
            $this->context
                ->buildViolation($constraint->minMessage)
                ->setParameter('{{ min }}', (string) $constraint->min)
                ->setInvalidValue($value)
                ->setCode(Password::MIN_ERROR)
                ->addViolation()
            ;
        }

        if ($constraint->mixedCase && 1 !== preg_match('/(\p{Ll}+.*\p{Lu})|(\p{Lu}+.*\p{Ll})/u', $value)) {
            $this->context
                ->buildViolation($constraint->mixedCaseMessage)
                ->setInvalidValue($value)
                ->setCode(Password::MIXED_CASE_ERROR)
                ->addViolation()
            ;
        }

        if ($constraint->letters && 1 !== preg_match('/\pL/u', $value)) {
            $this->context
                ->buildViolation($constraint->lettersMessage)
                ->setInvalidValue($value)
                ->setCode(Password::LETTERS_ERROR)
                ->addViolation()
            ;
        }

        if ($constraint->symbols && 1 !== preg_match('/\p{Z}|\p{S}|\p{P}/u', $value)) {
            $this->context
                ->buildViolation($constraint->symbolsMessage)
                ->setInvalidValue($value)
                ->setCode(Password::SYMBOLS_ERROR)
                ->addViolation()
            ;
        }

        if ($constraint->numbers && 1 !== preg_match('/\pN/u', $value)) {
            $this->context
                ->buildViolation($constraint->numbersMessage)
                ->setInvalidValue($value)
                ->setCode(Password::NUMBERS_ERROR)
                ->addViolation()
            ;
        }
    }
}

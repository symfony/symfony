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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author George Mponos <gmponos@gmail.com>
 */
class BitcoinAddressValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof BitcoinAddress) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\BitcoinAddress');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        if (!in_array($constraint->type, ['btc'], true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ type }}', $constraint->type)
                ->setCode(BitcoinAddress::TYPE_NOT_RECOGNIZED_ERROR)
                ->addViolation();
        }

        $value = (string)$value;

        if ('btc' === $constraint->type) {
            $code = $this->validateBtcAddress($value);
            if (true !== ($code)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $value)
                    ->setParameter('{{ type }}', $constraint->type)
                    ->setCode($code)
                    ->addViolation();
            }

            return;
        }
    }

    private function validateBtcAddress($value)
    {
        $len = strlen($value);
        if ($len < 25) {
            return BitcoinAddress::TOO_SHORT_ERROR;
        }

        if ($len > 34) {
            return BitcoinAddress::TOO_LONG_ERROR;
        }

        return (!preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $value, $matches));
    }
}
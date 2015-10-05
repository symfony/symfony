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

use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LengthValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Length) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Length');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $stringValue = (string) $value;
        $invalidCharset = false;

        if ('UTF8' === $charset = strtoupper($constraint->charset)) {
            $charset = 'UTF-8';
        }

        if ('UTF-8' === $charset) {
            if (!preg_match('//u', $stringValue)) {
                $invalidCharset = true;
            } elseif (function_exists('utf8_decode')) {
                $length = strlen(utf8_decode($stringValue));
            } else {
                preg_replace('/./u', '', $stringValue, -1, $length);
            }
        } elseif (function_exists('mb_strlen')) {
            if (@mb_check_encoding($stringValue, $constraint->charset)) {
                $length = mb_strlen($stringValue, $constraint->charset);
            } else {
                $invalidCharset = true;
            }
        } elseif (function_exists('iconv_strlen')) {
            $length = @iconv_strlen($stringValue, $constraint->charset);
            $invalidCharset = false === $length;
        } else {
            $length = strlen($stringValue);
        }

        if ($invalidCharset) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->charsetMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ charset }}', $constraint->charset)
                    ->setInvalidValue($value)
                    ->setCode(Length::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->charsetMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ charset }}', $constraint->charset)
                    ->setInvalidValue($value)
                    ->setCode(Length::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            }

            return;
        }

        if (null !== $constraint->max && $length > $constraint->max) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->maxMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ limit }}', $constraint->max)
                    ->setInvalidValue($value)
                    ->setPlural((int) $constraint->max)
                    ->setCode(Length::TOO_LONG_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->maxMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ limit }}', $constraint->max)
                    ->setInvalidValue($value)
                    ->setPlural((int) $constraint->max)
                    ->setCode(Length::TOO_LONG_ERROR)
                    ->addViolation();
            }

            return;
        }

        if (null !== $constraint->min && $length < $constraint->min) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->minMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ limit }}', $constraint->min)
                    ->setInvalidValue($value)
                    ->setPlural((int) $constraint->min)
                    ->setCode(Length::TOO_SHORT_ERROR)
                    ->addViolation();
            } else {
                $this->buildViolation($constraint->min == $constraint->max ? $constraint->exactMessage : $constraint->minMessage)
                    ->setParameter('{{ value }}', $this->formatValue($stringValue))
                    ->setParameter('{{ limit }}', $constraint->min)
                    ->setInvalidValue($value)
                    ->setPlural((int) $constraint->min)
                    ->setCode(Length::TOO_SHORT_ERROR)
                    ->addViolation();
            }
        }
    }
}

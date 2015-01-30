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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LengthValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
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

        if (function_exists('iconv_strlen')) {
            $length = @iconv_strlen($stringValue, $constraint->charset);
            $invalidCharset = false === $length;
        } elseif (function_exists('mb_strlen')) {
            if (mb_check_encoding($stringValue, $constraint->charset)) {
                $length = mb_strlen($stringValue, $constraint->charset);
            } else {
                $invalidCharset = true;
            }
        } elseif ('UTF-8' !== $charset) {
            $length = strlen($stringValue);
        } elseif (!preg_match('//u', $stringValue)) {
            $invalidCharset = true;
        } elseif (function_exists('utf8_decode')) {
            $length = strlen(utf8_decode($stringValue));
        } else {
            preg_replace('/./u', '', $stringValue, -1, $length);
        }

        if ($invalidCharset) {
            $this->context->addViolation($constraint->charsetMessage, array(
                '{{ value }}' => $this->formatValue($stringValue),
                '{{ charset }}' => $constraint->charset,
            ), $value);

            return;
        }

        if ($constraint->min == $constraint->max && $length != $constraint->min) {
            $this->context->addViolation($constraint->exactMessage, array(
                '{{ value }}' => $this->formatValue($stringValue),
                '{{ limit }}' => $constraint->min,
            ), $value, (int) $constraint->min);

            return;
        }

        if (null !== $constraint->max && $length > $constraint->max) {
            $this->context->addViolation($constraint->maxMessage, array(
                '{{ value }}' => $this->formatValue($stringValue),
                '{{ limit }}' => $constraint->max,
            ), $value, (int) $constraint->max);

            return;
        }

        if (null !== $constraint->min && $length < $constraint->min) {
            $this->context->addViolation($constraint->minMessage, array(
                '{{ value }}' => $this->formatValue($stringValue),
                '{{ limit }}' => $constraint->min,
            ), $value, (int) $constraint->min);
        }
    }
}

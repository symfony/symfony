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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LengthValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Length) {
            throw new UnexpectedTypeException($constraint, Length::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_scalar($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $stringValue = (string) $value;

        if (null !== $constraint->normalizer) {
            $stringValue = ($constraint->normalizer)($stringValue);
        }

        try {
            $invalidCharset = !@mb_check_encoding($stringValue, $constraint->charset);
        } catch (\ValueError $e) {
            if (!str_starts_with($e->getMessage(), 'mb_check_encoding(): Argument #2 ($encoding) must be a valid encoding')) {
                throw $e;
            }

            $invalidCharset = true;
        }

        $length = $invalidCharset ? 0 : match ($constraint->countUnit) {
            Length::COUNT_BYTES => \strlen($stringValue),
            Length::COUNT_CODEPOINTS => mb_strlen($stringValue, $constraint->charset),
            Length::COUNT_GRAPHEMES => grapheme_strlen($stringValue),
        };

        if ($invalidCharset || false === ($length ?? false)) {
            $this->context->buildViolation($constraint->charsetMessage)
                ->setParameter('{{ value }}', $this->formatValue($stringValue))
                ->setParameter('{{ charset }}', $constraint->charset)
                ->setInvalidValue($value)
                ->setCode(Length::INVALID_CHARACTERS_ERROR)
                ->addViolation();

            return;
        }

        if (null !== $constraint->max && $length > $constraint->max) {
            $exactlyOptionEnabled = $constraint->min == $constraint->max;

            $this->context->buildViolation($exactlyOptionEnabled ? $constraint->exactMessage : $constraint->maxMessage)
                ->setParameter('{{ value }}', $this->formatValue($stringValue))
                ->setParameter('{{ limit }}', $constraint->max)
                ->setParameter('{{ value_length }}', $length)
                ->setInvalidValue($value)
                ->setPlural($constraint->max)
                ->setCode($exactlyOptionEnabled ? Length::NOT_EQUAL_LENGTH_ERROR : Length::TOO_LONG_ERROR)
                ->addViolation();

            return;
        }

        if (null !== $constraint->min && $length < $constraint->min) {
            $exactlyOptionEnabled = $constraint->min == $constraint->max;

            $this->context->buildViolation($exactlyOptionEnabled ? $constraint->exactMessage : $constraint->minMessage)
                ->setParameter('{{ value }}', $this->formatValue($stringValue))
                ->setParameter('{{ limit }}', $constraint->min)
                ->setParameter('{{ value_length }}', $length)
                ->setInvalidValue($value)
                ->setPlural($constraint->min)
                ->setCode($exactlyOptionEnabled ? Length::NOT_EQUAL_LENGTH_ERROR : Length::TOO_SHORT_ERROR)
                ->addViolation();
        }
    }
}

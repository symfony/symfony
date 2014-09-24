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
 * Validates whether the value is a valid ISBN-10 or ISBN-13
 *
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Isbn) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Isbn');
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;
        $canonical = str_replace('-', '', $value);

        if (null == $constraint->type) {
            if ($constraint->isbn10 && !$constraint->isbn13) {
                $constraint->type = 'isbn10';
            } elseif ($constraint->isbn13 && !$constraint->isbn10) {
                $constraint->type = 'isbn13';
            }
        }

        // Explicitly validate against ISBN-10
        if ('isbn10' === $constraint->type) {
            if (!$this->validateIsbn10($canonical)) {
                $this->buildViolation($this->getMessage($constraint, $constraint->type))
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->addViolation();
            }

            return;
        }

        // Explicitly validate against ISBN-13
        if ('isbn13' === $constraint->type) {
            if (!$this->validateIsbn13($canonical)) {
                $this->buildViolation($this->getMessage($constraint, $constraint->type))
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->addViolation();
            }

            return;
        }

        // Try both ISBNs
        if (!$this->validateIsbn10($canonical) && !$this->validateIsbn13($canonical)) {
            $this->buildViolation($this->getMessage($constraint))
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->addViolation();
        }
    }

    protected function validateIsbn10($isbn)
    {
        $checkSum = 0;

        for ($i = 0; $i < 10; ++$i) {
            if (!isset($isbn{$i})) {
                return false;
            }

            if ('X' === $isbn{$i}) {
                $digit = 10;
            } elseif (ctype_digit($isbn{$i})) {
                $digit = $isbn{$i};
            } else {
                return false;
            }

            $checkSum += $digit * intval(10 - $i);
        }

        if (isset($isbn{$i})) {
            return false;
        }

        return 0 === $checkSum % 11;
    }

    protected function validateIsbn13($isbn)
    {
        if (!ctype_digit($isbn)) {
            return false;
        }

        $length = strlen($isbn);

        if ($length < 13) {
            return false;
        }

        if ($length > 13) {
            return false;
        }

        $checkSum = 0;

        for ($i = 0; $i < 13; $i += 2) {
            $checkSum += $isbn{$i};
        }

        for ($i = 1; $i < 12; $i += 2) {
            $checkSum += $isbn{$i}
            * 3;
        }

        return 0 === $checkSum % 10;
    }

    protected function getMessage($constraint, $type = null)
    {
        if (null !== $constraint->message) {
            return $constraint->message;
        } elseif ('isbn10' === $type) {
            return $constraint->isbn10Message;
        } elseif ('isbn13' === $type) {
            return $constraint->isbn13Message;
        }

        return $constraint->bothIsbnMessage;
    }
}

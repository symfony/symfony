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

use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RangeValidator extends ConstraintValidator
{
    private ?PropertyAccessorInterface $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @return void
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof Range) {
            throw new UnexpectedTypeException($constraint, Range::class);
        }

        if (null === $value) {
            return;
        }

        $min = $this->getLimit($constraint->minPropertyPath, $constraint->min, $constraint);
        $max = $this->getLimit($constraint->maxPropertyPath, $constraint->max, $constraint);

        if (!is_numeric($value) && !$value instanceof \DateTimeInterface) {
            if ($this->isParsableDatetimeString($min) && $this->isParsableDatetimeString($max)) {
                $this->context->buildViolation($constraint->invalidDateTimeMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            } else {
                $this->context->buildViolation($constraint->invalidMessage)
                    ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                    ->setCode(Range::INVALID_CHARACTERS_ERROR)
                    ->addViolation();
            }

            return;
        }

        // Convert strings to DateTimes if comparing another DateTime
        // This allows to compare with any date/time value supported by
        // the DateTime constructor:
        // https://php.net/datetime.formats
        if ($value instanceof \DateTimeInterface) {
            if (\is_string($min)) {
                try {
                    $min = new $value($min);
                } catch (\Exception) {
                    throw new ConstraintDefinitionException(\sprintf('The min value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $min, get_debug_type($value), get_debug_type($constraint)));
                }
            }

            if (\is_string($max)) {
                try {
                    $max = new $value($max);
                } catch (\Exception) {
                    throw new ConstraintDefinitionException(\sprintf('The max value "%s" could not be converted to a "%s" instance in the "%s" constraint.', $max, get_debug_type($value), get_debug_type($constraint)));
                }
            }
        }

        $hasLowerLimit = null !== $min;
        $hasUpperLimit = null !== $max;

        if ($hasLowerLimit && $hasUpperLimit && ($this->isGreaterThan($min, $value) || $this->isGreaterThan($value, $max))) {
            $message = $constraint->notInRangeMessage;
            $code = Range::NOT_IN_RANGE_ERROR;

            $violationBuilder = $this->context->buildViolation($message)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ min }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setParameter('{{ max }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode($code);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();

            return;
        }

        if ($hasUpperLimit && $this->isGreaterThan($value, $max)) {
            $violationBuilder = $this->context->buildViolation($constraint->maxMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($max, self::PRETTY_DATE))
                ->setCode(Range::TOO_HIGH_ERROR);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();

            return;
        }

        if ($hasLowerLimit && $this->isGreaterThan($min, $value)) {
            $violationBuilder = $this->context->buildViolation($constraint->minMessage)
                ->setParameter('{{ value }}', $this->formatValue($value, self::PRETTY_DATE))
                ->setParameter('{{ limit }}', $this->formatValue($min, self::PRETTY_DATE))
                ->setCode(Range::TOO_LOW_ERROR);

            if (null !== $constraint->maxPropertyPath) {
                $violationBuilder->setParameter('{{ max_limit_path }}', $constraint->maxPropertyPath);
            }

            if (null !== $constraint->minPropertyPath) {
                $violationBuilder->setParameter('{{ min_limit_path }}', $constraint->minPropertyPath);
            }

            $violationBuilder->addViolation();
        }
    }

    private function isGreaterThan(mixed $value, mixed $limit): bool
    {
        // PHP compares numeric strings beyond the int range as floats, so only those can lose precision
        if (!$this->isFloatString($value) && !$this->isFloatString($limit)) {
            return $value > $limit;
        }

        if ((!\is_int($value) && !\is_string($value)) || (!\is_int($limit) && !\is_string($limit))) {
            return $value > $limit;
        }

        $valueString = trim((string) $value, " \t\n\r\v\f");
        $limitString = trim((string) $limit, " \t\n\r\v\f");

        if (!preg_match('/^[+-]?[0-9]+$/D', $valueString) || !preg_match('/^[+-]?[0-9]+$/D', $limitString)) {
            return $value > $limit;
        }

        $valueDigits = ltrim(ltrim($valueString, '+-'), '0');
        $limitDigits = ltrim(ltrim($limitString, '+-'), '0');
        $valueNegative = '' !== $valueDigits && '-' === $valueString[0];
        $limitNegative = '' !== $limitDigits && '-' === $limitString[0];

        if ($valueNegative !== $limitNegative) {
            return $limitNegative;
        }

        $comparison = strnatcmp($valueDigits, $limitDigits);

        return $valueNegative ? $comparison < 0 : $comparison > 0;
    }

    private function isFloatString(mixed $value): bool
    {
        return \is_string($value) && is_numeric($value) && \is_float($value + 0);
    }

    private function getLimit(?string $propertyPath, mixed $default, Constraint $constraint): mixed
    {
        if (null === $propertyPath) {
            return $default;
        }

        if (null === $object = $this->context->getObject()) {
            return $default;
        }

        try {
            return $this->getPropertyAccessor()->getValue($object, $propertyPath);
        } catch (NoSuchPropertyException $e) {
            throw new ConstraintDefinitionException(\sprintf('Invalid property path "%s" provided to "%s" constraint: ', $propertyPath, get_debug_type($constraint)).$e->getMessage(), 0, $e);
        } catch (UninitializedPropertyException) {
            return null;
        }
    }

    private function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor ??= PropertyAccess::createPropertyAccessor();
    }

    private function isParsableDatetimeString(mixed $boundary): bool
    {
        if (null === $boundary) {
            return true;
        }

        if (!\is_string($boundary)) {
            return false;
        }

        try {
            new \DateTimeImmutable($boundary);
        } catch (\Exception) {
            return false;
        }

        return true;
    }
}
